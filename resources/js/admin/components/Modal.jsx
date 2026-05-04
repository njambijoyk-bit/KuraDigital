import React from 'react';
import { Dialog, DialogPanel, DialogTitle, DialogBackdrop } from '@headlessui/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function Modal({ open, onClose, title, children, size = 'md' }) {
    const sizes = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    };

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50" transition>
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-black/30 transition ease-out duration-200 data-[closed]:opacity-0"
            />

            <div className="fixed inset-0 overflow-y-auto">
                <div className="flex min-h-full items-center justify-center p-4">
                    <DialogPanel
                        transition
                        className={`w-full ${sizes[size]} bg-white rounded-xl shadow-xl transition ease-out duration-200 data-[closed]:opacity-0 data-[closed]:scale-95`}
                    >
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <DialogTitle className="text-lg font-heading font-semibold text-gray-900">
                                {title}
                            </DialogTitle>
                            <button
                                onClick={onClose}
                                className="text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                <XMarkIcon className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="px-6 py-4">{children}</div>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
