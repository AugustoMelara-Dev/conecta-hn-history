/**
 * Components/Dashboard/OnboardingWidget.jsx - Setup Progress Tracker
 *
 * FEATURES:
 * - Visual progress bar
 * - Actionable step cards
 * - Gamification for user activation
 *
 * @param {Object} props
 * @param {number} props.progress - 0-100 completion percentage
 * @param {Array} props.steps - [{ id, title, completed, href }]
 */

import { Link } from '@inertiajs/react';

export default function OnboardingWidget({ progress = 0, steps = [] }) {
    // Don't render if 100% complete
    if (progress >= 100) return null;

    const completedSteps = steps.filter(s => s.completed).length;
    const totalSteps = steps.length;

    return (
        <div className="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white mb-8">
            <div className="flex items-start justify-between mb-4">
                <div>
                    <h2 className="text-xl font-bold mb-1">🚀 Configura tu Tienda</h2>
                    <p className="text-indigo-100 text-sm">
                        Completa estos pasos para empezar a vender
                    </p>
                </div>
                <div className="text-right">
                    <span className="text-3xl font-bold">{progress}%</span>
                    <p className="text-indigo-200 text-xs">{completedSteps}/{totalSteps} pasos</p>
                </div>
            </div>

            {/* Progress Bar */}
            <div className="h-3 bg-white/20 rounded-full overflow-hidden mb-6">
                <div
                    className="h-full bg-white rounded-full transition-all duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>

            {/* Steps */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {steps.map((step) => (
                    <Link
                        key={step.id}
                        href={step.href}
                        className={`flex items-center gap-3 p-3 rounded-xl transition-all ${step.completed
                                ? 'bg-white/20 opacity-75'
                                : 'bg-white/10 hover:bg-white/20'
                            }`}
                    >
                        {/* Checkbox */}
                        <div className={`w-6 h-6 rounded-full flex items-center justify-center ${step.completed ? 'bg-emerald-400' : 'bg-white/30'
                            }`}>
                            {step.completed ? (
                                <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            ) : (
                                <span className="w-2 h-2 bg-white rounded-full" />
                            )}
                        </div>

                        {/* Label */}
                        <span className={`text-sm font-medium ${step.completed ? 'line-through' : ''}`}>
                            {step.title}
                        </span>
                    </Link>
                ))}
            </div>
        </div>
    );
}
