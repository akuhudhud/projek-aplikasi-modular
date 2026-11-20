
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import PaginationControls from '../../../src/components/shared/PaginationControls';

describe('PaginationControls', () => {
    it('does not render when there is only one page', () => {
        const { container } = render(
            <PaginationControls
                currentPage={1}
                lastPage={1}
                total={10}
                loading={false}
                onPageChange={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('renders current page and total record count', () => {
        render(
            <PaginationControls
                currentPage={2}
                lastPage={5}
                total={42}
                loading={false}
                onPageChange={vi.fn()}
            />,
        );

        expect(
            screen.getByText('Halaman 2 daripada 5 · 42 rekod'),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('navigation', {
                name: 'Navigasi halaman',
            }),
        ).toBeInTheDocument();
    });

    it('moves to the previous page', () => {
        const onPageChange = vi.fn();

        render(
            <PaginationControls
                currentPage={3}
                lastPage={5}
                total={42}
                loading={false}
                onPageChange={onPageChange}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'SEBELUMNYA' }),
        );

        expect(onPageChange).toHaveBeenCalledWith(2);
    });

    it('moves to the next page', () => {
        const onPageChange = vi.fn();

        render(
            <PaginationControls
                currentPage={3}
                lastPage={5}
                total={42}
                loading={false}
                onPageChange={onPageChange}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'SETERUSNYA' }),
        );

        expect(onPageChange).toHaveBeenCalledWith(4);
    });

    it('disables previous button on the first page', () => {
        render(
            <PaginationControls
                currentPage={1}
                lastPage={5}
                total={42}
                loading={false}
                onPageChange={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'SEBELUMNYA' }),
        ).toBeDisabled();
    });

    it('disables next button on the last page', () => {
        render(
            <PaginationControls
                currentPage={5}
                lastPage={5}
                total={42}
                loading={false}
                onPageChange={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'SETERUSNYA' }),
        ).toBeDisabled();
    });

    it('disables both buttons while loading', () => {
        render(
            <PaginationControls
                currentPage={2}
                lastPage={5}
                total={42}
                loading={true}
                onPageChange={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'SEBELUMNYA' }),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', { name: 'SETERUSNYA' }),
        ).toBeDisabled();
    });
});
