/**
 * @jest-environment jsdom
 */

import { fireEvent, render, screen } from '@testing-library/react';
import { ActionableAlert, EmptyState, LoadingState } from '@/Components/Feedback/UXStates';

describe('UX state components', () => {
    it('renders a loading state with an accessible status', () => {
        render(<LoadingState title="Actualizando resultados" testId="loading-check" />);

        expect(screen.getByTestId('loading-check')).toHaveAttribute('role', 'status');
        expect(screen.getByText('Actualizando resultados')).toBeInTheDocument();
    });

    it('renders an empty state with primary and secondary actions', () => {
        const onSecondaryAction = jest.fn();

        render(
            <EmptyState
                title="No hay productos"
                description="Agrega productos para continuar."
                actionLabel="Ver catalogo"
                actionHref="/demo"
                secondaryLabel="Limpiar filtros"
                onSecondaryAction={onSecondaryAction}
            />,
        );

        expect(screen.getByText('No hay productos')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /ver catalogo/i })).toHaveAttribute('href', '/demo');

        fireEvent.click(screen.getByRole('button', { name: /limpiar filtros/i }));
        expect(onSecondaryAction).toHaveBeenCalled();
    });

    it('renders actionable alert guidance and invokes the action', () => {
        const onAction = jest.fn();

        render(
            <ActionableAlert title="No pudimos confirmar" actionLabel="Revisar datos" onAction={onAction} tone="error">
                Verifica el telefono e intenta nuevamente.
            </ActionableAlert>,
        );

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByText(/verifica el telefono/i)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /revisar datos/i }));
        expect(onAction).toHaveBeenCalled();
    });
});
