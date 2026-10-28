<?php

namespace App\Console\Commands;

use App\Models\IdentityVerificationDocument;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EncryptLegacyIdentityDocuments extends Command
{
    private const ENCRYPTED_FILE_PREFIX = 'SRIDENC1:';

    protected $signature = 'identity:encrypt-legacy-documents
                            {--dry-run : Scan documents without changing files}
                            {--force : Confirm in-place encryption of legacy files}';

    protected $description =
        'Encrypt legacy identity documents stored as plaintext and verify the result.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !(bool) $this->option('force')) {
            $this->error(
                'No files were changed. Run with --dry-run first, then use --force to encrypt files.'
            );

            return self::FAILURE;
        }

        /*
         * Preflight all documents before mutating any file.
         *
         * This prevents a missing, unreadable or undecryptable existing
         * encrypted document later in the scan from being discovered only
         * after earlier plaintext files have already been encrypted.
         *
         * This is not a transaction over external storage. Storage can still
         * change between this pass and the migration pass.
         */
        $preflightFailed = false;

        IdentityVerificationDocument::query()->chunkById(
            100,
            function ($documents) use (&$preflightFailed): bool {
                foreach ($documents as $document) {
                    try {
                        $disk = Storage::disk($document->disk);

                        if (!$this->fileExists($disk, $document->path)) {
                            $preflightFailed = true;

                            $this->error(
                                'Preflight failed: a document file is missing. No files were changed.'
                            );

                            return false;
                        }

                        $contents = $disk->get($document->path);

                        if (!is_string($contents)) {
                            $preflightFailed = true;

                            $this->error(
                                'Preflight failed: a document could not be read. No files were changed.'
                            );

                            return false;
                        }

                        if (
                            str_starts_with(
                                $contents,
                                self::ENCRYPTED_FILE_PREFIX
                            )
                        ) {
                            try {
                                Crypt::decryptString(
                                    substr(
                                        $contents,
                                        strlen(self::ENCRYPTED_FILE_PREFIX)
                                    )
                                );
                            } catch (Throwable $exception) {
                                report($exception);

                                $preflightFailed = true;

                                $this->error(
                                    'Preflight failed: an encrypted document could not be verified. No files were changed.'
                                );

                                return false;
                            }
                        }
                    } catch (Throwable $exception) {
                        report($exception);

                        $preflightFailed = true;

                        $this->error(
                            'Preflight failed while checking document storage. No files were changed.'
                        );

                        return false;
                    }
                }

                return true;
            },
            'id'
        );

        if ($preflightFailed) {
            $this->error(
                'Migration aborted during preflight. Inspect storage and APP_KEY before retrying.'
            );

            return self::FAILURE;
        }

        $scanned = 0;
        $alreadyEncrypted = 0;
        $encrypted = 0;
        $failure = false;

        IdentityVerificationDocument::query()->chunkById(
            100,
            function ($documents) use (
                $dryRun,
                &$scanned,
                &$alreadyEncrypted,
                &$encrypted,
                &$failure
            ): bool {
                foreach ($documents as $document) {
                    $scanned++;

                    try {
                        $disk = Storage::disk($document->disk);

                        if (!$this->fileExists($disk, $document->path)) {
                            $failure = true;

                            $this->error(
                                'A document file is missing. Processing stopped; no further files were changed.'
                            );

                            return false;
                        }

                        $contents = $disk->get($document->path);

                        if (!is_string($contents)) {
                            $failure = true;

                            $this->error(
                                'A document could not be read. Processing stopped.'
                            );

                            return false;
                        }

                        if (
                            str_starts_with(
                                $contents,
                                self::ENCRYPTED_FILE_PREFIX
                            )
                        ) {
                            try {
                                Crypt::decryptString(
                                    substr(
                                        $contents,
                                        strlen(self::ENCRYPTED_FILE_PREFIX)
                                    )
                                );
                            } catch (Throwable $exception) {
                                report($exception);

                                $failure = true;

                                $this->error(
                                    'An encrypted document failed integrity verification. Processing stopped without overwriting it.'
                                );

                                return false;
                            }

                            $alreadyEncrypted++;

                            continue;
                        }

                        if ($dryRun) {
                            continue;
                        }

                        $encryptedContents = self::ENCRYPTED_FILE_PREFIX
                            .Crypt::encryptString($contents);

                        try {
                            $stored = $disk->put(
                                $document->path,
                                $encryptedContents
                            );

                            if ($stored !== true) {
                                throw new RuntimeException(
                                    'Encrypted document write failed.'
                                );
                            }

                            if (
                                !$this->fileExists(
                                    $disk,
                                    $document->path
                                )
                            ) {
                                throw new RuntimeException(
                                    'Encrypted document is missing after write.'
                                );
                            }

                            $readBack = $disk->get($document->path);

                            if (
                                !is_string($readBack)
                                || !str_starts_with(
                                    $readBack,
                                    self::ENCRYPTED_FILE_PREFIX
                                )
                            ) {
                                throw new RuntimeException(
                                    'Encrypted document verification failed.'
                                );
                            }

                            $decrypted = Crypt::decryptString(
                                substr(
                                    $readBack,
                                    strlen(self::ENCRYPTED_FILE_PREFIX)
                                )
                            );

                            if ($decrypted !== $contents) {
                                throw new RuntimeException(
                                    'Encrypted document content mismatch.'
                                );
                            }
                        } catch (Throwable $exception) {
                            /*
                             * Best-effort restoration of the exact original
                             * contents if writing or verification fails.
                             */
                            try {
                                $restored = $disk->put(
                                    $document->path,
                                    $contents
                                );

                                $restoreReadBack = $this->fileExists(
                                    $disk,
                                    $document->path
                                )
                                    ? $disk->get($document->path)
                                    : null;

                                if (
                                    $restored !== true
                                    || !is_string($restoreReadBack)
                                    || $restoreReadBack !== $contents
                                ) {
                                    $this->error(
                                        'CRITICAL: document restoration could not be verified. Stop processing and escalate to the storage operator.'
                                    );
                                } else {
                                    $this->error(
                                        'Encryption failed; the original file was restored and verified.'
                                    );
                                }
                            } catch (Throwable) {
                                $this->error(
                                    'CRITICAL: encryption failed and restoration could not be verified. Stop processing and escalate to the storage operator.'
                                );
                            }

                            report($exception);

                            $failure = true;

                            return false;
                        }

                        $encrypted++;
                    } catch (Throwable $exception) {
                        report($exception);

                        $failure = true;

                        $this->error(
                            'A document could not be processed. Processing stopped; inspect the operational logs.'
                        );

                        return false;
                    }
                }

                return true;
            },
            'id'
        );

        $this->line('Documents scanned: '.$scanned);
        $this->line('Already encrypted: '.$alreadyEncrypted);

        if ($dryRun) {
            $this->line(
                'Plaintext documents found: '
                .($scanned - $alreadyEncrypted)
            );

            if ($failure) {
                $this->error(
                    'Dry run stopped because a document could not be processed.'
                );

                return self::FAILURE;
            }

            $this->info(
                'Dry run completed. No document files were changed.'
            );

            return self::SUCCESS;
        }

        $this->line('Documents encrypted and verified: '.$encrypted);

        if ($failure) {
            $this->error(
                'Processing stopped after an error. Inspect storage before retrying.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Document processing completed. Every changed file was verified.'
        );

        return self::SUCCESS;
    }

    /**
     * Storage state may change between filesystem operations.
     *
     * @phpstan-impure
     */
    private function fileExists(
        FilesystemAdapter $disk,
        string $path
    ): bool {
        return $disk->exists($path);
    }
}
