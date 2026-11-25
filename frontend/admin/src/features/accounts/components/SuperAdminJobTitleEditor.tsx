interface Props {
  jobTitle: string;
  reason: string;
  updating: boolean;
  onJobTitleChange: (value: string) => void;
  onReasonChange: (value: string) => void;
  onSave: () => void;
}

export default function SuperAdminJobTitleEditor({
  jobTitle,
  reason,
  updating,
  onJobTitleChange,
  onReasonChange,
  onSave
}: Props) {
  return (
    <div className="admin-record">
      <h3>Kemas Kini Jawatan</h3>

      <label>
        Jawatan
        <input
          maxLength={100}
          value={jobTitle}
          disabled={updating}
          onChange={(event) => onJobTitleChange(event.target.value)}
        />
      </label>

      <label>
        Sebab Perubahan Jawatan
        <textarea
          rows={3}
          maxLength={1000}
          value={reason}
          disabled={updating}
          onChange={(event) => onReasonChange(event.target.value)}
        />
      </label>

      <button
        type="button"
        className="admin-button admin-button--primary"
        disabled={updating || !reason.trim()}
        onClick={onSave}
      >
        Simpan Jawatan
      </button>
    </div>
  );
}
