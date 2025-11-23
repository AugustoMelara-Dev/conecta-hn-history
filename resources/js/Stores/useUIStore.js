import { create } from 'zustand';

export const useUIStore = create((set) => ({
    // Login Modal State
    isLoginModalOpen: false,
    loginView: 'otp', // 'otp' | 'password'

    // Actions
    openLoginModal: () => set({ isLoginModalOpen: true }),
    closeLoginModal: () => set({ isLoginModalOpen: false }),
    setLoginView: (view) => set({ loginView: view }),

    // Post-login action (optional for future enhancement)
    postLoginAction: null,
    setPostLoginAction: (action) => set({ postLoginAction: action }),
    clearPostLoginAction: () => set({ postLoginAction: null }),
}));
