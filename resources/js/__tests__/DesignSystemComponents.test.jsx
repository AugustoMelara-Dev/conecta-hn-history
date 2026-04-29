/**
 * @jest-environment jsdom
 */

import { render, screen } from '@testing-library/react';
import { Breadcrumb, BreadcrumbItem, BreadcrumbList, BreadcrumbPage } from '@/Components/ui/breadcrumb';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { Progress } from '@/Components/ui/progress';

describe('premium design system primitives', () => {
    it('renders breadcrumb navigation with the current page', () => {
        render(
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbPage>Checkout</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>,
        );

        expect(screen.getByLabelText('breadcrumb')).toBeInTheDocument();
        expect(screen.getByText('Checkout')).toHaveAttribute('aria-current', 'page');
    });

    it('renders an empty state with title and description', () => {
        render(
            <Empty>
                <EmptyHeader>
                    <EmptyTitle>No hay datos</EmptyTitle>
                    <EmptyDescription>Agrega contenido para continuar.</EmptyDescription>
                </EmptyHeader>
            </Empty>,
        );

        expect(screen.getByText('No hay datos')).toBeInTheDocument();
        expect(screen.getByText('Agrega contenido para continuar.')).toBeInTheDocument();
    });

    it('clamps progress values to the accessible range', () => {
        render(<Progress value={140} max={100} />);

        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100');
    });
});
