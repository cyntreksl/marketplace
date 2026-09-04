import { Check } from 'lucide-react';
import { Fragment } from 'react';

type CheckoutStep = 'cart' | 'shipping' | 'payment' | 'review';

const steps: { key: CheckoutStep; label: string }[] = [
    { key: 'cart', label: 'Cart' },
    { key: 'shipping', label: 'Shipping' },
    { key: 'payment', label: 'Payment' },
    { key: 'review', label: 'Review & Place Order' },
];

export function CheckoutProgress({ current }: { current: CheckoutStep }) {
    const currentIndex = steps.findIndex((step) => step.key === current);

    return (
        <nav
            aria-label="Checkout progress"
            className="[scrollbar-width:none] overflow-x-auto border-b border-slate-200 pb-6"
        >
            <ol className="mx-auto flex max-w-4xl min-w-[42rem] items-start px-3">
                {steps.map((step, index) => {
                    const isActive = index === currentIndex;
                    const isComplete = index < currentIndex;

                    return (
                        <Fragment key={step.key}>
                            <li
                                className="grid w-36 shrink-0 justify-items-center gap-2 text-center"
                                aria-current={isActive ? 'step' : undefined}
                            >
                                <span
                                    className={`grid size-9 place-items-center rounded-full border text-xs font-black shadow-sm transition ${
                                        isActive
                                            ? 'border-[#ff5a00] bg-[#ff5a00] text-white shadow-orange-200'
                                            : isComplete
                                              ? 'border-orange-200 bg-orange-50 text-[#ff5a00]'
                                              : 'border-slate-200 bg-white text-slate-600'
                                    }`}
                                >
                                    {isComplete ? (
                                        <Check
                                            className="size-4"
                                            strokeWidth={3}
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span
                                    className={`text-xs font-bold ${
                                        isActive
                                            ? 'text-slate-950'
                                            : 'text-slate-600'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </li>
                            {index < steps.length - 1 && (
                                <li
                                    aria-hidden="true"
                                    className={`mt-[1.1rem] h-px min-w-10 flex-1 ${
                                        index < currentIndex
                                            ? 'bg-orange-200'
                                            : 'bg-slate-200'
                                    }`}
                                />
                            )}
                        </Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}
