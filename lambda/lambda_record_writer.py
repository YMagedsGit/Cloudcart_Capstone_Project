"""
CloudCart upload-record writer.

Trigger: S3 ObjectCreated:* notification on the uploads bucket.
Action: for each uploaded object, write a row into the `uploads` table in
RDS MySQL (bucket, key, size, timestamp), then publish a success message to
SNS. The `uploads` table itself is created ahead of time by the EC2
user-data script (CREATE TABLE IF NOT EXISTS), so this function only needs
to INSERT.

This function must be attached to the VPC (private app subnets) because it
opens a direct TCP connection to RDS on port 3306 - unlike an SSM-based
design, there's no control-plane API to go through here.

Required environment variables:
  DB_HOST, DB_USER, DB_PASS, DB_NAME - RDS connection details
  SNS_TOPIC_ARN                       - topic to publish the result to
"""

import os
import urllib.parse
import boto3
import pymysql

DB_HOST = os.environ["DB_HOST"]
DB_USER = os.environ["DB_USER"]
DB_PASS = os.environ["DB_PASS"]
DB_NAME = os.environ["DB_NAME"]
SNS_TOPIC_ARN = os.environ["SNS_TOPIC_ARN"]

s3 = boto3.client("s3")
sns = boto3.client("sns")


def handler(event, context):
    results = []

    for record in event.get("Records", []):
        bucket = record["s3"]["bucket"]["name"]
        key = urllib.parse.unquote_plus(record["s3"]["object"]["key"])
        size = record["s3"]["object"].get("size", 0)

        _write_record(bucket, key, size)
        _notify(bucket, key, size)
        results.append({"bucket": bucket, "key": key, "size": size})

    return {"processed": results}


def _write_record(bucket, key, size):
    conn = pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        connect_timeout=10,
    )
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "INSERT INTO uploads (bucket, object_key, size_bytes) VALUES (%s, %s, %s)",
                (bucket, key, size),
            )
        conn.commit()
        print(f"Recorded upload: s3://{bucket}/{key} ({size} bytes)")
    finally:
        conn.close()


def _notify(bucket, key, size):
    message = (
        f"New upload recorded in CloudCart.\n\n"
        f"File: s3://{bucket}/{key}\n"
        f"Size: {size} bytes\n"
    )
    sns.publish(
        TopicArn=SNS_TOPIC_ARN,
        Subject="CloudCart: upload recorded",
        Message=message,
    )
    print(f"Published SNS notification for {key}")
