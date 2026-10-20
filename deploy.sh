#!/bin/bash
# =============================================================================
# CloudCart deploy script.
#
# 1. Packages the Lambda function (with pymysql) into a zip.
# 2. Packages the PHP app (including vendor/) into a zip.
# 3. Uploads both to a small artifact bucket.
# 4. Runs `aws cloudformation deploy` with all parameters.
# =============================================================================
set -euo pipefail

REGION="eu-north-1"
STACK_NAME="cloudcart"

DB_MASTER_USERNAME="admin"
DB_MASTER_PASSWORD="${CC_DB_PASSWORD:?set CC_DB_PASSWORD}"
NOTIFICATION_EMAIL="${CC_NOTIFY_EMAIL:-you@example.com}"
ASG_TARGET_CPU="50"

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
ARTIFACT_BUCKET="cloudcart-lambda-artifacts-${ACCOUNT_ID}-${REGION}"
TIMESTAMP=$(date +%s)

LAMBDA_KEY="lambda/record-writer-${TIMESTAMP}.zip"
APP_KEY="app/cloudcart-app-${TIMESTAMP}.zip"
RELEASE_ID="rel-${TIMESTAMP}"

echo "=== Account: $ACCOUNT_ID | Region: $REGION | Stack: $STACK_NAME ==="
echo "=== Release: $RELEASE_ID ==="

# -----------------------------------------------------------------------------
# 1. Package the Lambda function
# -----------------------------------------------------------------------------
echo "Packaging Lambda..."
rm -rf build
mkdir -p build/lambda_pkg
cp lambda/lambda_record_writer.py build/lambda_pkg/
pip install pymysql --target build/lambda_pkg --quiet --break-system-packages 2>/dev/null || \
  pip install pymysql --target build/lambda_pkg --quiet
(cd build/lambda_pkg && zip -q -r ../lambda.zip .)
echo "  -> build/lambda.zip"

# -----------------------------------------------------------------------------
# 2. Package the PHP app
# -----------------------------------------------------------------------------
echo "Packaging PHP app..."
if [ ! -d app ]; then
  echo "ERROR: app/ directory not found." >&2
  exit 1
fi
if [ ! -f app/vendor/autoload.php ]; then
  echo "ERROR: app/vendor/autoload.php missing. Run inside app/: composer require firebase/php-jwt" >&2
  exit 1
fi
(cd app && zip -q -r ../build/app.zip . \
    -x "*.git*" "*.DS_Store" "vendor/bin/*" "*.md" "patcher.py" "callback.php")
echo "  -> build/app.zip"
echo "  contents (first 24 entries):"
unzip -l build/app.zip | awk 'NR<=24'

# -----------------------------------------------------------------------------
# 3. Create artifact bucket (if needed) and upload
# -----------------------------------------------------------------------------
if ! aws s3api head-bucket --bucket "$ARTIFACT_BUCKET" --region "$REGION" 2>/dev/null; then
  echo "Creating artifact bucket $ARTIFACT_BUCKET"
  aws s3api create-bucket --bucket "$ARTIFACT_BUCKET" --region "$REGION" \
    --create-bucket-configuration LocationConstraint="$REGION"
  aws s3api put-public-access-block --bucket "$ARTIFACT_BUCKET" \
    --public-access-block-configuration \
      BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
fi

aws s3 cp build/lambda.zip "s3://${ARTIFACT_BUCKET}/${LAMBDA_KEY}" --region "$REGION"
aws s3 cp build/app.zip    "s3://${ARTIFACT_BUCKET}/${APP_KEY}"    --region "$REGION"
echo "Uploaded:"
echo "  s3://${ARTIFACT_BUCKET}/${LAMBDA_KEY}"
echo "  s3://${ARTIFACT_BUCKET}/${APP_KEY}"

# -----------------------------------------------------------------------------
# 4. Deploy the stack
# -----------------------------------------------------------------------------
echo "Deploying CloudFormation stack (this takes a while)..."
aws cloudformation deploy \
  --template-file cloudcart.yaml \
  --stack-name "$STACK_NAME" \
  --region "$REGION" \
  --capabilities CAPABILITY_NAMED_IAM \
  --parameter-overrides \
      DBMasterUsername="$DB_MASTER_USERNAME" \
      DBMasterPassword="$DB_MASTER_PASSWORD" \
      NotificationEmail="$NOTIFICATION_EMAIL" \
      ASGTargetCpuUtilization="$ASG_TARGET_CPU" \
      ArtifactBucket="$ARTIFACT_BUCKET" \
      LambdaCodeKey="$LAMBDA_KEY" \
      AppCodeKey="$APP_KEY" \
      ReleaseId="$RELEASE_ID"

echo ""
echo "================= DEPLOY DONE ================="
aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$REGION" \
  --query 'Stacks[0].Outputs' --output table

echo ""
echo "Rolling the ASG so new instances pick up the new user-data..."
ASG_NAME=$(aws cloudformation describe-stack-resource \
  --stack-name "$STACK_NAME" --region "$REGION" \
  --logical-resource-id AutoScalingGroup \
  --query 'StackResourceDetail.PhysicalResourceId' --output text 2>/dev/null || echo "")

if [ -n "$ASG_NAME" ] && [ "$ASG_NAME" != "None" ]; then
  aws autoscaling start-instance-refresh \
    --auto-scaling-group-name "$ASG_NAME" \
    --preferences '{"MinHealthyPercentage": 0, "InstanceWarmup": 300}' \
    --region "$REGION" >/dev/null
  echo "  Started instance refresh on $ASG_NAME"
  echo "  Watch with: aws autoscaling describe-instance-refreshes --auto-scaling-group-name $ASG_NAME --region $REGION"
fi

echo ""
echo "Check $NOTIFICATION_EMAIL and confirm the SNS subscription."
echo "Storefront may take a few minutes to become healthy after the ASG refresh."
echo "================================================="

cat > stack-env.sh <<EOF
REGION="$REGION"
STACK_NAME="$STACK_NAME"
ARTIFACT_BUCKET="$ARTIFACT_BUCKET"
EOF