import './bootstrap';

const ready = (callback) => {
    if (document.readyState !== 'loading') {
        callback();
    } else {
        document.addEventListener('DOMContentLoaded', callback);
    }
};

ready(() => {
    // Sidebar (mobile)
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const setSidebar = (open) => {
        if (!sidebar) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        if (overlay) overlay.classList.toggle('hidden', !open);
    };
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const open = sidebar?.classList.contains('-translate-x-full');
            setSidebar(open);
        });
    });
    overlay?.addEventListener('click', () => setSidebar(false));

    // Confirmation dialogs
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Flash auto dismiss
    document.querySelectorAll('[data-flash]').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity .3s ease, transform .3s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-4px)';
            setTimeout(() => el.remove(), 320);
        }, 7000);
    });

    // Weight total calculator
    const weightScope = document.querySelector('[data-weight-scope]');
    if (weightScope) {
        const inputs = () => Array.from(weightScope.querySelectorAll('[data-weight-input]'));
        const update = () => {
            let total = 0;
            inputs().forEach((input) => {
                if (input.disabled) return;
                const row = input.closest('[data-criterion-row]');
                const enabled = row?.querySelector('[data-criteria-enabled]');
                if (enabled && !enabled.checked) return;
                total += parseFloat(input.value) || 0;
            });
            document.querySelectorAll('[data-weight-total]').forEach((target) => {
                target.textContent = total.toLocaleString('id-ID', { maximumFractionDigits: 2 });
            });
            const warning = document.querySelector('[data-weight-warning]');
            if (warning) {
                warning.classList.toggle('hidden', Math.abs(total - 100) < 0.001);
            }
        };
        weightScope.addEventListener('input', update);
        weightScope.addEventListener('change', (event) => {
            if (event.target.matches('[data-criteria-enabled]')) {
                const row = event.target.closest('[data-criterion-row]');
                const input = row?.querySelector('[data-weight-input]');
                if (input) input.disabled = !event.target.checked;
                row?.classList.toggle('opacity-50', !event.target.checked);
            }
            update();
        });
        update();

        const equalize = document.querySelector('[data-equalize]');
        equalize?.addEventListener('click', () => {
            const enabled = Array.from(weightScope.querySelectorAll('[data-criteria-enabled]')).filter((c) => c.checked);
            const per = enabled.length > 0 ? Math.round((100 / enabled.length) * 100) / 100 : 0;
            enabled.forEach((checkbox) => {
                const row = checkbox.closest('[data-criterion-row]');
                const input = row?.querySelector('[data-weight-input]');
                if (input) input.value = per;
            });
            update();
        });
    }

    // Logo preview
    document.querySelector('[data-logo-input]')?.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        const preview = document.querySelector('[data-logo-preview]');
        if (!file || !preview) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    });

    // Tabs
    document.querySelectorAll('[data-tabs]').forEach((group) => {
        const buttons = group.querySelectorAll('[data-tab-target]');
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                buttons.forEach((b) => b.classList.remove('active', 'bg-white', 'text-slate-900', 'shadow-sm'));
                buttons.forEach((b) => b.classList.add('text-slate-500'));
                button.classList.add('active', 'bg-white', 'text-slate-900', 'shadow-sm');
                button.classList.remove('text-slate-500');
                document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.tabPanel !== button.dataset.tabTarget);
                });
            });
        });
    });

    // AI narrative
    document.querySelectorAll('[data-ai-narrative]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.querySelector(button.dataset.aiNarrative);
            if (!target) return;
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = 'Menyusun narasi…';
            target.classList.remove('hidden');
            target.textContent = 'Menghubungi penyedia AI…';
            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Gagal membuat narasi AI.');
                target.textContent = data.narrative;
            } catch (error) {
                target.textContent = error.message;
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    // Test AI connection
    document.querySelector('[data-test-ai]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const target = document.querySelector('[data-test-ai-result]');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Menguji…';
        if (target) {
            target.classList.remove('hidden');
            target.textContent = 'Menghubungi penyedia AI…';
            target.className = 'mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600';
        }
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({}),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Koneksi gagal.');
            if (target) {
                target.textContent = 'Berhasil: ' + data.message;
                target.className = 'mt-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-700';
            }
        } catch (error) {
            if (target) {
                target.textContent = error.message;
                target.className = 'mt-3 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700';
            }
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    // Print
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    // Supplier selection counter
    const supplierChecks = document.querySelectorAll('[data-supplier-check]');
    const supplierCount = document.querySelector('[data-supplier-count]');
    if (supplierChecks.length && supplierCount) {
        const update = () => {
            const total = Array.from(supplierChecks).filter((c) => c.checked).length;
            document.querySelectorAll('[data-supplier-count]').forEach((target) => {
                target.textContent = total;
            });
        };
        supplierChecks.forEach((checkbox) => checkbox.addEventListener('change', update));
        update();
    }

    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                const label = button.querySelector('[data-copy-label]');
                if (label) {
                    const original = label.textContent;
                    label.textContent = 'Tersalin';
                    setTimeout(() => (label.textContent = original), 1400);
                }
            } catch (error) {
                // Clipboard might be unavailable; ignore.
            }
        });
    });
});
