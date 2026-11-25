import { useEffect, useRef, useState } from 'react';

import {
  getApiError,
  getRunnerLicenseDocument,
  getRunnerLicenseVerification
} from '../../../services/adminApi';

import type {
  RunnerLicenseVerificationDetail
} from '../../../types/admin';

export interface RunnerDocumentPreview {
  accountId: string;
  url: string;
  mimeType: string;
}

interface UseRunnerLicenseOptions {
  canReview: boolean;
}

export default function useRunnerLicense({
  canReview
}: UseRunnerLicenseOptions) {
  const [licenseDetails, setLicenseDetails] = useState<
    Record<string, RunnerLicenseVerificationDetail>
  >({});

  const [licenseLoadingId, setLicenseLoadingId] = useState('');
  const [documentLoadingId, setDocumentLoadingId] = useState('');
  const [documentPreview, setDocumentPreview] =
    useState<RunnerDocumentPreview | null>(null);
  const [error, setError] = useState('');

  const mountedRef = useRef(false);
  const licenseRequestId = useRef(0);
  const documentRequestId = useRef(0);
  const previewUrlRef = useRef<string | null>(null);

  function clearDocumentPreview() {
    if (previewUrlRef.current) {
      window.URL.revokeObjectURL(previewUrlRef.current);
      previewUrlRef.current = null;
    }

    setDocumentPreview(null);
  }

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      licenseRequestId.current += 1;
      documentRequestId.current += 1;

      if (previewUrlRef.current) {
        window.URL.revokeObjectURL(previewUrlRef.current);
        previewUrlRef.current = null;
      }
    };
  }, []);

  async function showLicenseDetails(accountId: string) {
    if (!canReview || licenseLoadingId) return;

    const requestId = ++licenseRequestId.current;

    setLicenseLoadingId(accountId);
    setError('');

    try {
      const detail = await getRunnerLicenseVerification(accountId);

      if (!mountedRef.current || requestId !== licenseRequestId.current) {
        return;
      }

      setLicenseDetails((current) => ({
        ...current,
        [accountId]: detail
      }));
    } catch (err) {
      if (mountedRef.current && requestId === licenseRequestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current && requestId === licenseRequestId.current) {
        setLicenseLoadingId('');
      }
    }
  }

  async function showLicenseDocument(accountId: string) {
    if (!canReview || documentLoadingId) return;

    const requestId = ++documentRequestId.current;

    setDocumentLoadingId(accountId);
    setError('');
    clearDocumentPreview();

    try {
      const blob = await getRunnerLicenseDocument(accountId);

      if (!mountedRef.current || requestId !== documentRequestId.current) {
        return;
      }

      const url = window.URL.createObjectURL(blob);
      previewUrlRef.current = url;

      setDocumentPreview({
        accountId,
        url,
        mimeType: blob.type || 'application/octet-stream'
      });
    } catch (err) {
      if (mountedRef.current && requestId === documentRequestId.current) {
        setError(getApiError(err));
      }
    } finally {
      if (mountedRef.current && requestId === documentRequestId.current) {
        setDocumentLoadingId('');
      }
    }
  }

  function resetLicenseRequests() {
    licenseRequestId.current += 1;
    documentRequestId.current += 1;

    setLicenseLoadingId('');
    setDocumentLoadingId('');
    clearDocumentPreview();
  }

  return {
    licenseDetails,
    licenseLoadingId,
    documentLoadingId,
    documentPreview,
    error,
    setError,
    showLicenseDetails,
    showLicenseDocument,
    clearDocumentPreview,
    resetLicenseRequests
  };
}
