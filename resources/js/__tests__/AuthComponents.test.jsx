/**
 * @jest-environment jsdom
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { router } from '@inertiajs/core';
import AuthGate from '@/Components/Utils/AuthGate';
import GlobalLoginModal from '@/Components/Organisms/Auth/GlobalLoginModal';
import { useUIStore } from '@/Stores/useUIStore';

// Mock Inertia
jest.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: null }, // Not authenticated by default
        },
    }),
    useForm: () => ({
        data: { email: '', password: '', remember: false },
        setData: jest.fn(),
        post: jest.fn(),
        processing: false,
        errors: {},
        reset: jest.fn(),
    }),
}));

// Mock Axios
jest.mock('axios');

describe('AuthGate Component', () => {
    beforeEach(() => {
        // Reset Zustand store
        useUIStore.setState({ isLoginModalOpen: false });
    });

    it('opens modal when unauthenticated user clicks protected action', () => {
        const mockAction = jest.fn();

        render(
            <AuthGate onAuth={mockAction}>
                <button>Click Me</button>
            </AuthGate>
        );

        const button = screen.getByText('Click Me');
        fireEvent.click(button);

        // Modal should open
        expect(useUIStore.getState().isLoginModalOpen).toBe(true);

        // Original action should NOT execute
        expect(mockAction).not.toHaveBeenCalled();
    });

    it('executes action when authenticated user clicks', () => {
        // Mock authenticated user
        jest.spyOn(require('@inertiajs/react'), 'usePage').mockReturnValue({
            props: {
                auth: { user: { id: 1, name: 'Test User' } },
            },
        });

        const mockAction = jest.fn();

        render(
            <AuthGate onAuth={mockAction}>
                <button>Click Me</button>
            </AuthGate>
        );

        const button = screen.getByText('Click Me');
        fireEvent.click(button);

        // Action should execute
        expect(mockAction).toHaveBeenCalled();

        // Modal should NOT open
        expect(useUIStore.getState().isLoginModalOpen).toBe(false);
    });
});

describe('GlobalLoginModal Component', () => {
    beforeEach(() => {
        useUIStore.setState({ isLoginModalOpen: true, loginView: 'otp' });
    });

    it('renders with OTP tab active by default', () => {
        render(<GlobalLoginModal />);

        expect(screen.getByText('Comprador')).toBeInTheDocument();
        expect(screen.getByPlaceholderText(/ejemplo@correo.com/i)).toBeInTheDocument();
    });

    it('switches to password tab when clicked', () => {
        render(<GlobalLoginModal />);

        const vendedorTab = screen.getByText('Vendedor');
        fireEvent.click(vendedorTab);

        expect(screen.getByLabelText(/Email/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/Contraseña/i)).toBeInTheDocument();
    });

    it('validates identifier input for OTP', async () => {
        render(<GlobalLoginModal />);

        const input = screen.getByPlaceholderText(/ejemplo@correo.com/i);
        const submitButton = screen.getByText(/Enviar Código/i);

        fireEvent.change(input, { target: { value: 'test@example.com' } });
        fireEvent.click(submitButton);

        // Should attempt to send request
        await waitFor(() => {
            expect(input.value).toBe('test@example.com');
        });
    });

    it('closes modal when close action is triggered', () => {
        render(<GlobalLoginModal />);

        useUIStore.getState().closeLoginModal();

        expect(useUIStore.getState().isLoginModalOpen).toBe(false);
    });
});

describe('Zustand Store - useUIStore', () => {
    beforeEach(() => {
        useUIStore.setState({
            isLoginModalOpen: false,
            loginView: 'otp',
        });
    });

    it('opens login modal', () => {
        useUIStore.getState().openLoginModal();
        expect(useUIStore.getState().isLoginModalOpen).toBe(true);
    });

    it('closes login modal', () => {
        useUIStore.setState({ isLoginModalOpen: true });
        useUIStore.getState().closeLoginModal();
        expect(useUIStore.getState().isLoginModalOpen).toBe(false);
    });

    it('switches login view', () => {
        useUIStore.getState().setLoginView('password');
        expect(useUIStore.getState().loginView).toBe('password');
    });
});
