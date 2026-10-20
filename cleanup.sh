#!/bin/bash
# Tears down the CloudCart stack. S3 buckets with objects in them block
# CloudFormation's own delete, so we empty the UploadsBucket first.
set -e

if [ ! -f stack-env.sh ]; then
  echo "stack-env.sh not found - run this from the directory deploy.sh left it in."
  exit 1
fi
source stack-env.sh

echo "=== Emptying the stack-managed UploadsBucket (if it still exists) ==="
UPLOADS_BUCKET=$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$REGION" \
  --query "Stacks[0].Outputs[?OutputKey=='UploadsBucketName'].OutputValue" --output text 2>/dev/null || true)

if [ -n "$UPLOADS_BUCKET" ] && [ "$UPLOADS_BUCKET" != "None" ]; then
  echo "Emptying s3://$UPLOADS_BUCKET"
  aws s3 rm "s3://${UPLOADS_BUCKET}" --recursive --region "$REGION" 2>/dev/null || true
fi

echo "=== Deleting stack $STACK_NAME ==="
aws cloudformation delete-stack --stack-name "$STACK_NAME" --region "$REGION"
echo "Waiting for stack deletion to complete (RDS teardown takes the longest)..."
aws cloudformation wait stack-delete-complete --stack-name "$STACK_NAME" --region "$REGION"
echo "Stack deleted."

echo "=== Emptying and deleting the Lambda artifact bucket ==="
aws s3 rm "s3://${ARTIFACT_BUCKET}" --recursive --region "$REGION" 2>/dev/null || true
aws s3api delete-bucket --bucket "$ARTIFACT_BUCKET" --region "$REGION" 2>/dev/null || true

echo "Cleanup complete."
