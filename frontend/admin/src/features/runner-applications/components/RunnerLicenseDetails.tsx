import type { RunnerLicenseVerificationDetail } from '../../../types/admin';

import {
  formatRunnerBytes,
  formatRunnerDate
} from '../utils/runnerApplicationUtils';

interface RunnerLicenseDetailsProps {
  detail: RunnerLicenseVerificationDetail;
}

export default function RunnerLicenseDetails({
  detail
}: RunnerLicenseDetailsProps) {
  return (
    <div className="admin-record__details">
      <div>
        <small>Nombor Lesen</small>
        <strong>{detail.license_number_masked || '—'}</strong>
      </div>

      <div>
        <small>Jenis Lesen</small>
        <strong>{detail.license_type || '—'}</strong>
      </div>

      <div>
        <small>Kelas Lesen</small>
        <strong>{detail.license_class || '—'}</strong>
      </div>

      <div>
        <small>Tarikh Hantar</small>
        <strong>{formatRunnerDate(detail.submitted_at)}</strong>
      </div>

      <div>
        <small>Saiz Dokumen</small>
        <strong>{formatRunnerBytes(detail.size_bytes)}</strong>
      </div>

      <div>
        <small>Tarikh Semakan</small>
        <strong>{formatRunnerDate(detail.reviewed_at)}</strong>
      </div>
    </div>
  );
}
