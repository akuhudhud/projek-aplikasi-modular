interface RunnerDocumentPreviewProps {
  url: string;
  mimeType: string;
  onClose: () => void;
}

export default function RunnerDocumentPreview({
  url,
  mimeType,
  onClose
}: RunnerDocumentPreviewProps) {
  return (
    <section
      className="admin-document-preview"
      aria-label="Paparan dokumen lesen Runner"
    >
      <div className="admin-record__heading">
        <strong>Dokumen Lesen Runner</strong>

        <button
          type="button"
          className="admin-button"
          onClick={onClose}
        >
          Tutup Paparan
        </button>
      </div>

      {mimeType.startsWith('image/') ? (
        <img
          src={url}
          alt="Dokumen lesen Runner"
          style={{
            display: 'block',
            maxWidth: '100%',
            maxHeight: '640px',
            objectFit: 'contain',
            margin: '12px auto'
          }}
        />
      ) : mimeType === 'application/pdf' ? (
        <iframe
          title="Paparan dokumen lesen Runner"
          src={`${url}#toolbar=0&navpanes=0`}
          style={{
            display: 'block',
            width: '100%',
            minHeight: '560px',
            border: '1px solid #d1d5db',
            borderRadius: '8px'
          }}
        />
      ) : (
        <p role="alert">
          Format dokumen ini tidak disokong untuk paparan.
        </p>
      )}
    </section>
  );
}
