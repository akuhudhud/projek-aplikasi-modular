
# Identity Document Encryption Runbook

## 1. Purpose

This runbook defines the controlled procedure for migrating legacy plaintext identity documents to the encrypted storage format used by Super Runner.

The migration command is:

`php artisan identity:encrypt-legacy-documents`

The command supports a non-mutating `--dry-run` mode and requires `--force` before changing files.

The command performs a preflight validation pass before beginning migration writes. This reduces the risk of partially migrating documents when an existing file is missing, unreadable, corrupt or encrypted with an unavailable key.

This runbook does not authorise an untested production migration.

## 2. Security requirements

- Identity documents must remain in private storage.
- Never copy identity documents into source control, tickets, chat messages or ordinary logs.
- Never expose permanent public URLs for identity documents.
- Backups must be encrypted at rest and accessible only to authorised personnel.
- The production `APP_KEY` must be backed up using the approved secret-management process.
- Never put the actual `APP_KEY` in this runbook, source control, shell history or operational notes.
- Do not change or regenerate `APP_KEY` as part of this migration.
- Do not run the migration until backup restoration and key recovery have been tested.

## 3. Understand the storage format and preflight

Newly submitted identity documents are stored with the prefix:

`SRIDENC1:`

The content following this prefix is encrypted using Laravel's `Crypt::encryptString()`.

Legacy documents without this prefix are treated as plaintext by the current download endpoint for backward compatibility.

The migration command encrypts legacy files in place and verifies that the encrypted content can be decrypted to the exact original bytes.

Before writing any migrated files, the command performs a preflight pass over the document records. It checks that referenced files can be accessed and that documents already marked with the encrypted prefix can be decrypted and verified with the currently configured application key.

If preflight detects a missing or unreadable file, or an existing encrypted document that fails decryption or integrity verification, the command must fail before beginning migration writes.

The command is designed to preserve existing document paths and database metadata.

Preflight is not a transaction and does not guarantee that every subsequent storage operation will succeed. Storage failures, permission changes or other unexpected conditions during the migration pass can still occur after earlier documents have been migrated.

## 4. APP_KEY protection and recovery

The application encryption key is required to decrypt identity documents encrypted using Laravel Crypt.

Before migration:

1. Identify the exact production `APP_KEY` currently in use.
2. Confirm that the key is stored in the approved secret manager or protected deployment configuration.
3. Confirm that authorised recovery personnel can retrieve the correct key during an incident.
4. Verify the documented recovery procedure in an isolated environment.
5. Confirm that the key backup is protected independently from the application database and document backup.
6. Record only the secret reference and recovery procedure, never the key value itself.

Do not rotate, regenerate or replace `APP_KEY` during the migration.

If `APP_KEY` is lost or changed unexpectedly, stop document migration and download operations. Restore the correct key through the approved secret-management procedure before attempting decryption.

Do not overwrite encrypted documents merely because they cannot be decrypted with the currently configured key.

Any future key rotation requires a separately designed and tested migration procedure that can decrypt existing content and re-encrypt it under the replacement key without losing access to documents.

## 5. Pre-migration checks

Before running the command:

1. Confirm the production deployment and release version.
2. Confirm the migration has been reviewed and approved by the responsible operator.
3. Confirm no unrelated deployment or storage migration is running.
4. Confirm database backup completion.
5. Confirm private-storage backup completion for every disk referenced by the document records.
6. Confirm backups are encrypted and access-controlled.
7. Restore the database and representative identity documents in an isolated environment.
8. Confirm restored document bytes match the originals.
9. Confirm the correct `APP_KEY` can decrypt existing encrypted documents.
10. Confirm sufficient storage capacity and operational time are available.
11. Confirm a rollback and incident-escalation owner is available.

A successful backup command or checksum alone does not prove recoverability. An isolated restore test is required.

## 6. Run a dry run

From the deployed backend directory, run:

```bash
php artisan identity:encrypt-legacy-documents --dry-run
```

Review the command output and application logs.

The dry run must not modify document files. It must complete the applicable validation checks without writing migrated content.

Investigate any missing file, unreadable document or encrypted document that fails integrity verification. Do not continue until the cause is understood and resolved.

Record the non-sensitive command outcome, deployment reference, operator and timestamp.

Do not place document contents, MyKad numbers or encryption keys in the record.

## 7. Production migration approval

Before using `--force`, verify all of the following:

- [ ] The correct production release is deployed.
- [ ] The dry run completed successfully.
- [ ] The database backup is complete and protected.
- [ ] All relevant private-storage objects are backed up.
- [ ] Database and storage restore tests passed in an isolated environment.
- [ ] The current `APP_KEY` is protected and recoverable.
- [ ] The migration has an approved maintenance and incident-response plan.
- [ ] The responsible operator has authorised the migration.

If any item is incomplete, do not run the mutating command.

## 8. Execute the migration

Only after all approvals and checks pass, run:

```bash
php artisan identity:encrypt-legacy-documents --force
```

The command performs preflight validation before beginning migration writes. If preflight detects an existing problem, it should return failure without changing any document files.

Monitor the complete command output and operational logs.

Preflight does not make the full migration atomic. A failure during the subsequent migration pass can occur after earlier documents have already been encrypted. Do not assume that a failed command means every document remains unchanged or that the entire migration was rolled back.

If the command fails:

1. Stop further migration attempts.
2. Preserve the command output and non-sensitive operational logs.
3. Inspect the affected files and current database metadata.
4. Determine which files were migrated, restored or left in an uncertain state.
5. Compare affected files with the protected backup.
6. Escalate any unverified restoration or missing document.
7. Do not repeatedly rerun the command until the state is understood.

## 9. Post-migration verification

After a successful command:

1. Confirm the command reported completion without errors.
2. Run the dry run again and confirm there are no remaining legacy plaintext documents among the scanned records.
3. Confirm encrypted documents pass integrity verification.
4. Confirm authorised document downloads return the original file bytes.
5. Confirm unauthorised and cross-account document downloads remain blocked.
6. Confirm missing or corrupted encrypted files fail safely without returning ciphertext.
7. Confirm private storage remains private and response cache protections remain enabled.
8. Confirm document paths and database metadata remain consistent.
9. Review logs for errors or sensitive-data leakage.
10. Record the result without recording identity-document contents or secret values.

Do not declare success if any document is missing, unreadable or cannot be verified.

## 10. Recovery

If a document becomes unavailable or fails integrity verification:

1. Stop further changes to the affected document.
2. Preserve the current file for investigation using approved private-storage procedures.
3. Verify the configured `APP_KEY` against the approved secret-management record.
4. Restore the correct key if the key was changed or lost.
5. If the document is damaged, restore its original object from the protected backup using the approved recovery procedure.
6. Verify the restored document bytes and database metadata.
7. Test authorised download and access-control behaviour.
8. Document the incident without copying sensitive identity data into the incident record.

Do not restore an entire production database or replace multiple storage objects without an approved recovery plan.

## 11. Release acceptance checklist

- [ ] Migration command and tests are included in the approved release.
- [ ] CI passes on the exact candidate commit.
- [ ] Backup encryption and access controls are confirmed.
- [ ] Database restore test passes.
- [ ] Private-storage restore test passes.
- [ ] APP_KEY recovery is verified.
- [ ] Dry run completes successfully.
- [ ] Production migration is explicitly authorised.
- [ ] Migration command completes successfully.
- [ ] Post-migration dry run finds no remaining legacy plaintext documents.
- [ ] Authorised download and access-control tests pass.
- [ ] No unresolved missing, corrupt or unreadable documents remain.
- [ ] Operational outcome is recorded without sensitive data.

This runbook describes the required operational controls. It does not prove that a production backup, restore test or migration has actually been completed.
