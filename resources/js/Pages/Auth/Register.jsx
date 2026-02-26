/**
 * Register.jsx - Atomic Onboarding Registration Form
 * 
 * Implements Business Context Registration:
 * 1. User credentials (name, email, password)
 * 2. Tenant identity (tenant_name, slug)
 * 3. Reactive slug generation from business name
 */
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Store, Globe, User, Mail, Lock } from 'lucide-react';

/**
 * Generate URL-safe slug from business name
 * - Lowercase
 * - Replace spaces with dashes
 * - Remove special characters
 * - Max 50 characters
 */
const generateSlug = (name) => {
    if (!name) return '';

    return name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove diacritics (á → a)
        .replace(/[^a-z0-9\s-]/g, '')    // Remove special chars
        .replace(/\s+/g, '-')             // Spaces to dashes
        .replace(/-+/g, '-')              // Multiple dashes to single
        .replace(/^-|-$/g, '')            // Trim leading/trailing dashes
        .substring(0, 50);                // Max length
};

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        // NEW: Tenant identity fields
        business_name: '',
        slug: '',
    });

    // Reactive slug generation when business_name changes
    const handleBusinessNameChange = (value) => {
        setData((prev) => ({
            ...prev,
            business_name: value,
            slug: generateSlug(value),
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Registrar tu Negocio" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                    Crea tu cuenta gratis
                </h1>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Registra tu negocio y empieza a vender hoy
                </p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                {/* ═══════════════════════════════════════════════════════════
                    SECTION 1: Business Identity
                ═══════════════════════════════════════════════════════════ */}
                <div className="p-4 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 border border-blue-100 dark:border-gray-600">
                    <div className="flex items-center gap-2 mb-4 text-blue-700 dark:text-blue-400">
                        <Store className="w-5 h-5" />
                        <span className="font-medium">Información del Negocio</span>
                    </div>

                    {/* Tenant Name */}
                    <div>
                        <InputLabel htmlFor="business_name" value="Nombre de tu Negocio *" />
                        <TextInput
                            id="business_name"
                            name="business_name"
                            value={data.business_name}
                            className="mt-1 block w-full"
                            placeholder="Ej: Pizzería Don Juan"
                            onChange={(e) => handleBusinessNameChange(e.target.value)}
                            required
                        />
                        <InputError message={errors.business_name} className="mt-2" />
                    </div>

                    {/* Slug */}
                    <div className="mt-4">
                        <InputLabel htmlFor="slug">
                            <span className="flex items-center gap-2">
                                <Globe className="w-4 h-4" />
                                Tu Dirección Web Única *
                            </span>
                        </InputLabel>
                        <div className="mt-1 flex rounded-lg shadow-sm">
                            <span className="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                                {typeof window !== 'undefined' ? window.location.host : 'conectahn.com'}/
                            </span>
                            <TextInput
                                id="slug"
                                name="slug"
                                value={data.slug}
                                className="flex-1 rounded-l-none"
                                placeholder="pizzeria-don-juan"
                                onChange={(e) => setData('slug', generateSlug(e.target.value))}
                                required
                            />
                        </div>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Esta será la URL permanente de tu negocio
                        </p>
                        <InputError message={errors.slug} className="mt-2" />
                    </div>
                </div>

                {/* ═══════════════════════════════════════════════════════════
                    SECTION 2: User Credentials
                ═══════════════════════════════════════════════════════════ */}
                <div className="space-y-4">
                    {/* Name */}
                    <div>
                        <InputLabel htmlFor="name">
                            <span className="flex items-center gap-2">
                                <User className="w-4 h-4" />
                                Tu Nombre Completo *
                            </span>
                        </InputLabel>
                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            autoComplete="name"
                            placeholder="Juan Pérez"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    {/* Email */}
                    <div>
                        <InputLabel htmlFor="email">
                            <span className="flex items-center gap-2">
                                <Mail className="w-4 h-4" />
                                Correo Electrónico *
                            </span>
                        </InputLabel>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            placeholder="juan@ejemplo.com"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    {/* Password */}
                    <div>
                        <InputLabel htmlFor="password">
                            <span className="flex items-center gap-2">
                                <Lock className="w-4 h-4" />
                                Contraseña *
                            </span>
                        </InputLabel>
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirmar Contraseña *" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            placeholder="Repite tu contraseña"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                {/* ═══════════════════════════════════════════════════════════
                    SECTION 3: Actions
                ═══════════════════════════════════════════════════════════ */}
                <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <PrimaryButton className="w-full justify-center py-3" disabled={processing}>
                        {processing ? 'Creando tu negocio...' : 'Crear mi cuenta'}
                    </PrimaryButton>

                    <p className="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                        ¿Ya tienes cuenta?{' '}
                        <Link
                            href={route('login')}
                            className="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            Inicia sesión
                        </Link>
                    </p>
                </div>
            </form>
        </GuestLayout>
    );
}
