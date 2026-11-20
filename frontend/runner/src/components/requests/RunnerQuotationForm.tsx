
import type { FormEvent } from 'react';

interface RunnerQuotationFormProps {
    quotationAmount: string;
    quotationMessage: string;
    quotationLoading: boolean;
    hasPreviousQuotation: boolean;
    previousQuotationStatus: string;
    onAmountChange: (value: string) => void;
    onMessageChange: (value: string) => void;
    onClose: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

export default function RunnerQuotationForm({
    quotationAmount,
    quotationMessage,
    quotationLoading,
    hasPreviousQuotation,
    previousQuotationStatus,
    onAmountChange,
    onMessageChange,
    onClose,
    onSubmit,
}: RunnerQuotationFormProps) {
    return (
        <form
            className="runner-dashboard__quotation-form"
            onSubmit={onSubmit}
        >
            {hasPreviousQuotation && (
                <p className="runner-dashboard__notice">
                    Sebut harga terdahulu: {previousQuotationStatus}.
                    Anda boleh menghantar sebut harga baharu.
                </p>
            )}

            <label>
                Jumlah sebut harga (RM)
                <input
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                    value={quotationAmount}
                    onChange={(event) =>
                        onAmountChange(event.target.value)
                    }
                    placeholder="Contoh: 15.00"
                />
            </label>

            <label>
                Mesej kepada pelanggan (pilihan)
                <textarea
                    rows={3}
                    value={quotationMessage}
                    onChange={(event) =>
                        onMessageChange(event.target.value)
                    }
                    placeholder="Maklumat tambahan..."
                />
            </label>

            <div className="runner-dashboard__quotation-actions">
                <button
                    type="button"
                    onClick={onClose}
                    disabled={quotationLoading}
                >
                    BATAL
                </button>

                <button
                    type="submit"
                    disabled={quotationLoading}
                >
                    {quotationLoading
                        ? 'MENGHANTAR...'
                        : 'HANTAR SEBUT HARGA'}
                </button>
            </div>
        </form>
    );
}
