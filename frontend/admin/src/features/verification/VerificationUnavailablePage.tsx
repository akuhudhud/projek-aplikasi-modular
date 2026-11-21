
interface VerificationUnavailablePageProps {
  type: 'identity' | 'license';
}

export default function VerificationUnavailablePage({
  type
}: VerificationUnavailablePageProps) {
  const identity = type === 'identity';

  return (
    <section className="admin-page">
      <div className="admin-page__heading">
        <div>
          <span className="dashboard-page__eyebrow">
            MODUL PENGESAHAN
          </span>

          <h2>
            {identity ? 'Pengesahan Identiti' : 'Pengesahan Lesen'}
          </h2>

          <p>
            {identity
              ? 'Semakan dokumen dan maklumat identiti pengguna.'
              : 'Semakan dokumen lesen Runner.'}
          </p>
        </div>
      </div>

      <div className="admin-state admin-state--notice">
        <h3>API senarai belum tersedia</h3>
        <p>
          Backend pada masa ini menyediakan API butiran berdasarkan ID
          akaun, tetapi belum menyediakan endpoint senarai atau queue
          untuk modul ini.
        </p>
        <p>
          Paparan senarai akan diaktifkan selepas endpoint backend
          disediakan. Tiada rekod contoh atau data rekaan digunakan.
        </p>
      </div>
    </section>
  );
}
