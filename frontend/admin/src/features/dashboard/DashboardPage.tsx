
export default function DashboardPage() {
  return (
    <section className="dashboard-page">
      <div className="dashboard-page__welcome">
        <div>
          <span className="dashboard-page__eyebrow">
            SUPER RUNNER MANAGEMENT
          </span>

          <h2>Selamat datang</h2>

          <p>
            Pusat pengurusan dan pemantauan operasi SUPER RUNNER.
          </p>
        </div>

        <div className="dashboard-page__version">
          <span>VERSI</span>
          <strong>0.4.7</strong>
        </div>
      </div>

      <div className="dashboard-section-heading">
        <div>
          <h3>Ringkasan Operasi</h3>
          <p>
            Data akan dipaparkan selepas sambungan API dashboard
            tersedia.
          </p>
        </div>
      </div>

      <div className="dashboard-empty">
        <div className="dashboard-empty__icon">SR</div>

        <h3>Dashboard sedang disediakan</h3>

        <p>
          Tiada data operasi dipaparkan buat masa ini.
          Sistem tidak menggunakan data contoh atau statistik
          rekaan.
        </p>
      </div>
    </section>
  );
}
