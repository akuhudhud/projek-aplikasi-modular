
# Identity Data Deletion Runbook

## Purpose

This runbook defines the manual backup, deletion, verification and recovery procedure for identity-verification data in Super Runner.

Identity-data deletion is a sensitive administrative operation. The application attempts to restore deleted files when the database transaction fails, but database transactions cannot make external file storage atomic. Operators must therefore prepare and verify recovery options before authorising deletion.

## Scope

This procedure applies to the manual endpoint:

`DELETE /api/admin/identity-verifications/{accountId}/data`

The caller must have the `identity_verifications.delete_data` permission. Application-level authorisation and Runner-status protections remain mandatory.

This procedure does not introduce automatic retention or scheduled deletion.

## 1. Pre-deletion checks

Before executing a deletion:

1. Confirm the target account ID and the reason for deletion.
2. Confirm the operator is authorised to perform this action.
3. Confirm the target Runner status permits deletion. The application must reject deletion while the Runner status is `PENDING` or `APPROVED`.
4. Confirm the target identity-verification record and its associated document metadata.
5. Identify every storage disk referenced by the target document records. Do not assume all documents use the same disk.
6. Confirm the database and relevant private-storage backup procedures are available.
7. Confirm there is sufficient protected storage for the backup.
8. Record the operation reference and planned time in the authorised operational record. Do not copy MyKad numbers, identity documents or unverified Real Names into notes, tickets or ordinary audit records.

If the target, permissions, storage disks or recovery procedure cannot be verified, stop and escalate before deletion.

## 2. Create a protected database backup

Use the approved database backup mechanism for the deployed environment.

For a MySQL deployment, a logical backup can be created using `mysqldump`. Obtain connection settings from the approved secret-management mechanism; never commit credentials or place them in a command recorded in shell history.

Example using environment variables already supplied securely to the operator's shell:

```bash
set -eu
umask 077

BACKUP_DIR="/secure-backup/super-runner-identity"
mkdir -p "$BACKUP_DIR"

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"

export MYSQL_PWD="$DB_PASSWORD"

mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  "$DB_DATABASE" \
  > "$BACKUP_DIR/database.sql"

unset MYSQL_PWD
test -s "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" \
  > "$BACKUP_DIR/database.sql.sha256"
sha256sum --check "$BACKUP_DIR/database.sql.sha256"
```

Adjust the command to the actual database engine, deployment topology and backup policy. Confirm that the database version and options are compatible with the deployed MySQL version.

The example creates an unencrypted logical dump. Before using it in production, ensure the backup destination is protected by approved encryption at rest and access controls. Do not store the dump in the application repository, public web directory or an unapproved personal device.

A successful checksum confirms file integrity relative to the generated checksum; it does not prove that the database backup can be restored.

## 3. Back up private identity documents

1. Retrieve the target document metadata using an authorised, access-controlled process.
2. Identify the storage disk and exact object path for each document.
3. Back up the affected objects using the storage provider's approved snapshot, versioning or backup mechanism.
4. Preserve the original object paths and sufficient metadata to restore the documents to their expected locations.
5. Protect the backup with encryption, restricted access and an approved retention policy.
6. Verify that every expected document is included and that the backup can be read.
7. Record checksums or use the storage provider's integrity verification mechanism where supported.

Do not create public URLs or make private documents publicly accessible to perform this procedure.

The application's in-memory compensation mechanism is not a substitute for an independent backup, especially for large files, remote object storage or process interruption.

## 4. Verify recoverability

Before deletion, confirm that:

- The database backup exists and its integrity check passes.
- The relevant private documents have been backed up.
- The backup can be accessed by authorised recovery personnel.
- A restore test has been performed in an isolated environment according to the organisation's recovery policy.
- The restored test data and files are readable and consistent.
- The backup location, access restrictions and recovery owner are documented in the authorised operational record.

A backup that has not been restore-tested must not be treated as proven recoverable.

## 5. Execute the deletion

1. Reconfirm the target account ID and approved reason.
2. Submit the deletion request through the authorised administrative client.
3. Do not bypass the API permission checks or invoke direct database deletion as a workaround.
4. Capture the HTTP outcome and non-sensitive operation reference.
5. If the operation fails, do not repeatedly retry without inspecting the failure and current database/storage state.

The application is expected to validate the target, protect eligible Runner records, inspect associated files, delete document files and database records, and record a non-sensitive audit event within its deletion workflow.

## 6. Verify the outcome

After a successful response:

1. Confirm the identity-verification record is absent.
2. Confirm its associated document metadata is absent.
3. Confirm each targeted original object is absent from its configured storage disk.
4. Confirm the deletion audit event exists and does not contain identity-document contents, MyKad numbers or other prohibited sensitive data.
5. Confirm the API does not expose deleted identity data.
6. Record the outcome, timestamp, target account reference and operator in the authorised operational record without duplicating sensitive identity data.

If any check fails, treat the operation as unresolved. Restrict further deletion activity for the target and escalate to the responsible operator.

## 7. Failure and recovery

If the application reports a failed deletion:

1. Do not assume the operation was fully rolled back merely because an error response was returned.
2. Inspect the identity-verification record and associated document metadata using authorised access.
3. Check the original storage paths and compare file contents or provider checksums against the protected backup.
4. If the application reports that restoration could not be verified, escalate immediately. The database may have retained its records while one or more files remain missing or incorrect.
5. Use the approved recovery procedure to restore missing or corrupted files and verify their contents.
6. If database restoration is required, use the approved database recovery process. Avoid restoring an entire production database over live data without an approved recovery plan.
7. Reconcile database records, document metadata, storage objects and audit evidence before declaring recovery complete.
8. Document the result without copying sensitive identity information into incident notes.

Do not delete backup copies as part of the application deletion request. Backup disposition follows the separately approved backup and retention policy.

## 8. Security and audit requirements

- Keep backups private and encrypted at rest.
- Restrict backup access to authorised operational personnel.
- Never place credentials, MyKad numbers or document contents in source control.
- Never use permanent public URLs for identity documents.
- Do not place sensitive identity data in routine logs, analytics, error messages or ordinary audit reasons.
- Follow the organisation's approved backup retention and secure-disposal policy.
- Keep a minimal operational record of the action and outcome without reproducing deleted identity data.

## 9. Release acceptance checklist

- [ ] Database backup created and integrity checked.
- [ ] Relevant private-storage objects backed up.
- [ ] Restore test verified in an isolated environment.
- [ ] Target and operator permissions verified.
- [ ] Deletion performed through the authorised API.
- [ ] Database records and document metadata checked.
- [ ] Original storage objects checked.
- [ ] Audit record checked for sensitive-data leakage.
- [ ] Failure recovery escalated and verified if required.
- [ ] Operational outcome recorded without sensitive identity data.

This runbook documents the operational procedure. It does not by itself prove that a production backup has been created, a restore test has passed, or a deletion has been executed.
