// ============================================================
// FILE: js/voucher-checkout.js
// DESCRIPTION: Reusable Voucher Checkout Widget
//   - Call initVoucherCheckout(prefix, totalAmount, targetType)
//     when Step 4 becomes active.
//   - Read window._appliedVoucher[prefix] to get the current
//     applied voucher { id, code, discountAmount, finalTotal }
// ============================================================

window._appliedVoucher = window._appliedVoucher || {};

// ── Inline Step-2 Voucher Panel ──────────────────────────────
// Renders directly into a placeholder div on Step 2.
// Parameters:
//   prefix       - e.g. 'home', 'foreign', 'flash', 'local'
//   totalAmount  - raw booking total (before discount)
//   targetType   - service type string
//   containerId  - id of the placeholder div in Step 2 HTML
//   totalElId    - id of the span showing the live total
//   currencyFn   - () => currency symbol string
window.initVoucherCheckoutInline = function(prefix, totalAmount, targetType, containerId, totalElId, currencyFn, packageId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    window._vcTotalAmount     = window._vcTotalAmount     || {};
    window._vcRawTotalAmount  = window._vcRawTotalAmount  || {};
    window._vcTargetType      = window._vcTargetType      || {};
    window._vcPackageId       = window._vcPackageId       || {};
    window._vcTotalAmount[prefix]     = totalAmount;
    window._vcRawTotalAmount[prefix]  = totalAmount;
    window._vcTargetType[prefix]      = targetType || '';
    window._vcPackageId[prefix]       = packageId || 0;
    window._vcTotalElId      = window._vcTotalElId      || {};
    window._vcCurrencyFn     = window._vcCurrencyFn     || {};
    window._vcTotalElId[prefix]       = totalElId;
    window._vcCurrencyFn[prefix]      = currencyFn || (() => '₱');

    // Render panel (always refresh so traveler count changes are reflected)
    container.innerHTML = _vcBuildInlineHTML(prefix, totalAmount);

    // Load wallet vouchers into the panel
    _vcLoadInlineVouchers(prefix);
};

function _vcBuildInlineHTML(prefix, total) {
    const sym = (window._vcCurrencyFn && window._vcCurrencyFn[prefix]) ? window._vcCurrencyFn[prefix]() : '₱';
    const applied = window._appliedVoucher && window._appliedVoucher[prefix];
    return `
    <div class="vc-inline-panel" id="${prefix}VcInlinePanel">
        <div class="vc-inline-header">
            <i class="fas fa-ticket-alt"></i>
            <span>Vouchers & Discounts</span>
            ${applied ? `<span class="vc-inline-applied-badge"><i class="fas fa-check-circle"></i> ${_vcEsc(applied.code)} Applied</span>` : ''}
        </div>
        <div class="vc-inline-list" id="${prefix}VcInlineList">
            <div class="vc-loading"><i class="fas fa-spinner fa-spin"></i> Loading vouchers...</div>
        </div>
        <div id="${prefix}VcInlineMsg"></div>
        ${applied ? `
        <div class="vc-inline-savings">
            <i class="fas fa-tag"></i>
            You save <strong>${sym}${_vcFormatMoney(applied.discountAmount)}</strong> — Final total: <strong>${sym}${_vcFormatMoney(applied.finalTotal)}</strong>
            <button class="vc-inline-remove-btn" onclick="removeCheckoutVoucherInline('${prefix}')">✕ Remove</button>
        </div>` : ''}
    </div>`;
}

function _vcLoadInlineVouchers(prefix) {
    const list = document.getElementById(prefix + 'VcInlineList');
    if (!list) return;
    const applied = window._appliedVoucher && window._appliedVoucher[prefix];
    const sym = (window._vcCurrencyFn && window._vcCurrencyFn[prefix]) ? window._vcCurrencyFn[prefix]() : '₱';

    fetch('api/user_voucher_api.php?action=get_my_vouchers')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                // Not logged in or error
                list.innerHTML = `<div class="vc-inline-empty"><i class="fas fa-lock"></i> <a href="User Account/login.php" style="color:#003580;font-weight:700;">Log in</a> to use your vouchers.</div>`;
                return;
            }
            if (!res.data || res.data.length === 0) {
                list.innerHTML = `<div class="vc-inline-empty"><i class="fas fa-tags"></i> No vouchers in your wallet. <a href="index.php#voucher-center" target="_blank" style="color:#ff9800;font-weight:700;">Claim vouchers →</a></div>`;
                return;
            }
            const usable = res.data.filter(v => parseInt(v.is_used) !== 1);
            if (usable.length === 0) {
                list.innerHTML = `<div class="vc-inline-empty">All your vouchers have been used. <a href="index.php#voucher-center" target="_blank" style="color:#ff9800;font-weight:700;">Get more →</a></div>`;
                return;
            }

            list.innerHTML = usable.map(v => {
                const isApplied = applied && applied.id == v.id;
                const notApplicable = !isApplied && !isVoucherApplicable(v, prefix);
                const discVal = parseFloat(v.discount_value);
                const valStr  = v.discount_type === 'percentage' ? `${discVal}% OFF` : `${sym}${_vcFormatMoney(discVal)} OFF`;
                const minSpend = parseFloat(v.minimum_spend);
                const minStr   = minSpend > 0 ? `Min. ${sym}${_vcFormatMoney(minSpend)}` : 'No min. spend';
                return `
                <div class="vc-inline-item ${isApplied ? 'applied' : ''}${notApplicable ? ' disabled' : ''}" id="${prefix}VcInlineItem${v.id}">
                    <div class="vc-inline-badge">${valStr}</div>
                    <div class="vc-inline-info">
                        <div class="vc-inline-name">${_vcEsc(v.voucher_name)}</div>
                        <div class="vc-inline-meta">${_vcEsc(v.voucher_code)} &nbsp;·&nbsp; ${minStr}</div>
                    </div>
                    ${isApplied
                        ? `<button class="vc-inline-btn remove" onclick="removeCheckoutVoucherInline('${prefix}')"><i class="fas fa-times"></i> Remove</button>`
                        : notApplicable
                            ? `<button class="vc-inline-btn disabled" disabled>Not available</button>`
                            : `<button class="vc-inline-btn use" onclick="applyInlineVoucher('${prefix}', ${v.id}, '${_vcEsc(v.voucher_code)}')"><i class="fas fa-check"></i> Use</button>`
                    }
                </div>`;
            }).join('') + `<div style="text-align:right;margin-top:6px;"><a href="index.php#voucher-center" target="_blank" style="font-size:0.7rem;color:#ff9800;font-weight:700;"><i class="fas fa-plus-circle"></i> Claim more vouchers</a></div>`;
        })
        .catch(() => {
            list.innerHTML = `<div class="vc-inline-empty">Could not load vouchers. Please refresh.</div>`;
        });
}

window.applyInlineVoucher = function(prefix, voucherId, voucherCode, optionalTotal) {
    const total      = (optionalTotal !== undefined)
        ? optionalTotal
        : (window._vcRawTotalAmount && typeof window._vcRawTotalAmount[prefix] !== 'undefined')
            ? window._vcRawTotalAmount[prefix]
            : (window._vcTotalAmount[prefix] || 0);
    const targetType = window._vcTargetType[prefix]  || '';
    const msgEl      = document.getElementById(prefix + 'VcInlineMsg');
    if (msgEl) msgEl.innerHTML = `<div class="vc-msg info"><i class="fas fa-spinner fa-spin"></i> Validating...</div>`;

    const fd = new FormData();
    fd.append('action',       'validate_voucher');
    fd.append('voucher_id',   voucherId);
    fd.append('total_amount', total);
    fd.append('travelers',    _vcGetTravelerCount(prefix));
    fd.append('target_type',  targetType);
    fd.append('package_id',   window._vcPackageId && window._vcPackageId[prefix] ? window._vcPackageId[prefix] : 0);

    fetch('api/user_voucher_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (msgEl) msgEl.innerHTML = `<div class="vc-msg error">${res.message || 'Voucher cannot be applied.'}</div>`;
                if (optionalTotal !== undefined) {
                    removeCheckoutVoucherInline(prefix, total);
                }
                return;
            }
            const d = res.data;
            window._appliedVoucher[prefix] = {
                id:                     d.voucher_id,
                code:                   d.voucher_code,
                name:                   d.voucher_name,
                discountAmount:         d.discount_amount,
                finalTotal:             d.final_amount,
                originalTotal:          total,
                eligibleTravelers:      d.eligible_travelers || 0,
                maxDiscountedTravelers: d.max_discounted_travelers || 0
            };
            if (window._vcTotalAmount) window._vcTotalAmount[prefix] = d.final_amount;
            if (window._vcRawTotalAmount) window._vcRawTotalAmount[prefix] = total;

            if (msgEl) msgEl.innerHTML = `<div class="vc-msg success">✅ Voucher "${d.voucher_code}" applied! You save ₱${_vcFormatMoney(d.discount_amount)}.</div>`;

            const totalElId = window._vcTotalElId && window._vcTotalElId[prefix];
            const sym = (window._vcCurrencyFn && window._vcCurrencyFn[prefix]) ? window._vcCurrencyFn[prefix]() : '₱';
            if (totalElId) {
                const totalEl = document.getElementById(totalElId);
                if (totalEl) {
                    totalEl.innerHTML = `${sym}${_vcFormatMoney(d.final_amount)} <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${sym}${_vcFormatMoney(total)}</span>`;
                }
            }

            const containerId = prefix + 'Step2VoucherArea';
            const container = document.getElementById(containerId);
            if (container) container.innerHTML = _vcBuildInlineHTML(prefix, d.final_amount);
            _vcUpdateVoucherDisplays(prefix, d.final_amount, total);
            _vcShowPriceSummary(prefix, total, d.discount_amount, d.final_amount, d.eligible_travelers, d.max_discounted_travelers);
            _vcLoadInlineVouchers(prefix);
        })
        .catch(() => {
            if (msgEl) msgEl.innerHTML = `<div class="vc-msg error">Server error. Please try again.</div>`;
        });
};

window.updateVoucherTotalInline = function(prefix, newTotal) {
    if (!window._vcTotalAmount) return;
    window._vcTotalAmount[prefix] = newTotal;
    if (window._vcRawTotalAmount) window._vcRawTotalAmount[prefix] = newTotal;
    const oldApplied = window._appliedVoucher && window._appliedVoucher[prefix];
    if (oldApplied) {
        const rawTotal = newTotal;
        const msgEl = document.getElementById(prefix + 'VcInlineMsg');
        if (msgEl) msgEl.innerHTML = `<div class="vc-msg info"><i class="fas fa-spinner fa-spin"></i> Revalidating voucher...</div>`;
        applyInlineVoucher(prefix, oldApplied.id, oldApplied.code, rawTotal);
    } else {
        const containerId = prefix + 'Step2VoucherArea';
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = _vcBuildInlineHTML(prefix, newTotal);
            _vcLoadInlineVouchers(prefix);
        }
    }
};

window.removeCheckoutVoucherInline = function(prefix, fallbackTotal) {
    const origTotal = (typeof fallbackTotal !== 'undefined')
        ? fallbackTotal
        : (window._vcRawTotalAmount && window._vcRawTotalAmount[prefix])
            || window._appliedVoucher[prefix]?.originalTotal
            || window._vcTotalAmount[prefix] || 0;
    window._appliedVoucher[prefix] = null;
    if (window._vcTotalAmount) window._vcTotalAmount[prefix] = origTotal;
    if (window._vcRawTotalAmount) window._vcRawTotalAmount[prefix] = origTotal;

    // Restore total display
    const sym = (window._vcCurrencyFn && window._vcCurrencyFn[prefix]) ? window._vcCurrencyFn[prefix]() : '₱';
    const totalElId = window._vcTotalElId && window._vcTotalElId[prefix];
    if (totalElId) {
        const totalEl = document.getElementById(totalElId);
        if (totalEl) totalEl.textContent = `${sym}${_vcFormatMoney(origTotal)}`;
    }
    _vcUpdateVoucherDisplays(prefix, origTotal, origTotal);

    // Rebuild panel
    const containerId = prefix + 'Step2VoucherArea';
    const container = document.getElementById(containerId);
    if (container) container.innerHTML = _vcBuildInlineHTML(prefix, origTotal);
    _vcLoadInlineVouchers(prefix);
};

// ── Inline panel CSS (injected once alongside main CSS) ──────
(function injectInlineCSS() {
    if (document.getElementById('vcInlineCSS')) return;
    const s = document.createElement('style');
    s.id = 'vcInlineCSS';
    s.textContent = `
        .vc-inline-panel {
            background: linear-gradient(135deg, #f0f4ff 0%, #fff 100%);
            border: 1.5px solid #c7d7f8;
            border-radius: 14px;
            overflow: hidden;
            font-family: inherit;
        }
        .vc-inline-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px 8px;
            font-weight: 700;
            font-size: 0.82rem;
            color: #003580;
            border-bottom: 1px solid #e0e8ff;
            background: #eef2ff;
        }
        .vc-inline-header i { color: #ff9800; }
        .vc-inline-applied-badge {
            margin-left: auto;
            background: #dcfce7;
            color: #15803d;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .vc-inline-list {
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 190px;
            overflow-y: auto;
        }
        .vc-inline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 10px;
            transition: all 0.2s;
        }
        .vc-inline-item:hover  { border-color: #003580; background: #f0f4ff; }
        .vc-inline-item.applied { border-color: #22c55e; background: #f0fdf4; }
        .vc-inline-item.disabled { border-color: #cbd5e1; background: #f8fafc; color: #6b7280; }
        .vc-inline-item.disabled .vc-inline-badge { background: #94a3b8; color: #e2e8f0; }
        .vc-inline-item.disabled .vc-inline-name,
        .vc-inline-item.disabled .vc-inline-meta { color: #6b7280; }
        .vc-inline-item.disabled .vc-inline-btn { cursor: not-allowed; }
        .vc-inline-badge {
            background: #003580;
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 5px 9px;
            border-radius: 8px;
            white-space: nowrap;
            min-width: 60px;
            text-align: center;
        }
        .vc-inline-info { flex: 1; min-width: 0; }
        .vc-inline-name { font-size: 0.76rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .vc-inline-meta { font-size: 0.63rem; color: #94a3b8; font-family: monospace; }
        .vc-inline-btn {
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.18s;
        }
        .vc-inline-btn.use    { background: #003580; color: white; }
        .vc-inline-btn.use:hover    { background: #ff9800; }
        .vc-inline-btn.remove { background: #fee2e2; color: #dc2626; }
        .vc-inline-btn.remove:hover { background: #dc2626; color: white; }
        .vc-inline-btn.disabled { background: #cbd5e1; color: #475569; cursor: not-allowed; }
        .vc-inline-empty {
            font-size: 0.74rem;
            color: #64748b;
            padding: 8px 2px;
            text-align: center;
        }
        .vc-inline-savings {
            background: linear-gradient(90deg, #f0fdf4, #dcfce7);
            border-top: 1px solid #bbf7d0;
            padding: 8px 14px;
            font-size: 0.76rem;
            color: #15803d;
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }
        .vc-inline-savings i { color: #22c55e; }
        .vc-inline-remove-btn {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 0.68rem;
            color: #dc2626;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
        }
    `;
    document.head.appendChild(s);
})();

// ── CSS (injected once) ─────────────────────────────────────
(function injectVoucherCheckoutCSS() {
    if (document.getElementById('voucherCheckoutCSS')) return;
    const s = document.createElement('style');
    s.id = 'voucherCheckoutCSS';
    s.textContent = `
        .vc-wrapper {
            margin: 0 0 18px 0;
            border: 1.5px dashed #003580;
            border-radius: 14px;
            overflow: hidden;
            background: linear-gradient(135deg, #f0f4ff, #fff);
        }
        .vc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 15px;
            cursor: pointer;
            user-select: none;
        }
        .vc-header-left {
            display: flex;
            align-items: center;
            gap: 9px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #003580;
        }
        .vc-header-left i { color: #ff9800; font-size: 1rem; }
        .vc-chevron { color: #003580; transition: transform 0.25s; font-size: 0.8rem; }
        .vc-chevron.open { transform: rotate(180deg); }
        .vc-body {
            display: none;
            padding: 0 15px 14px;
        }
        .vc-body.open { display: block; }

        /* Manual input row */
        .vc-input-row {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        .vc-input-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.8rem;
            outline: none;
            font-family: monospace;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .vc-input-row input:focus { border-color: #003580; }
        .vc-apply-btn {
            background: #003580;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .vc-apply-btn:hover { background: #ff9800; }
        .vc-apply-btn:disabled { background: #94a3b8; cursor: not-allowed; }

        /* Wallet voucher list */
        .vc-wallet-label {
            font-size: 0.72rem;
            color: #64748b;
            margin-bottom: 7px;
            font-weight: 600;
        }
        .vc-voucher-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
        }
        .vc-voucher-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 9px 12px;
            cursor: pointer;
            transition: all 0.2s;
            gap: 10px;
        }
        .vc-voucher-item:hover { border-color: #003580; background: #f0f4ff; }
        .vc-voucher-item.applied { border-color: #22c55e; background: #f0fdf4; }
        .vc-vi-left { display: flex; align-items: center; gap: 10px; }
        .vc-vi-badge {
            background: #003580;
            color: white;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.85rem;
            font-weight: 800;
            white-space: nowrap;
            min-width: 56px;
            text-align: center;
        }
        .vc-vi-info { display: flex; flex-direction: column; gap: 1px; }
        .vc-vi-name { font-size: 0.78rem; font-weight: 700; color: #1e293b; }
        .vc-vi-code { font-size: 0.65rem; color: #64748b; font-family: monospace; }
        .vc-vi-min  { font-size: 0.62rem; color: #94a3b8; }
        .vc-vi-btn {
            border: none;
            padding: 5px 13px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .vc-vi-btn.apply  { background: #003580; color: white; }
        .vc-vi-btn.apply:hover  { background: #ff9800; }
        .vc-vi-btn.remove { background: #fee2e2; color: #dc2626; }
        .vc-vi-btn.remove:hover { background: #dc2626; color: white; }

        /* Applied summary banner */
        .vc-applied-banner {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #22c55e;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 8px;
        }
        .vc-applied-banner strong { font-weight: 800; }
        .vc-remove-link {
            background: none;
            border: none;
            color: #dc2626;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: underline;
        }

        /* Price summary update */
        .vc-price-summary {
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 10px;
            font-size: 0.78rem;
        }
        .vc-price-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            color: #64748b;
        }
        .vc-price-row.discount { color: #22c55e; font-weight: 700; }
        .vc-price-row.final    { color: #003580; font-weight: 800; font-size: 0.88rem; border-top: 1px solid #e2e8f0; margin-top: 4px; padding-top: 6px; }

        .vc-msg { font-size: 0.72rem; margin-top: 6px; padding: 5px 10px; border-radius: 6px; }
        .vc-msg.error   { background: #fee2e2; color: #dc2626; }
        .vc-msg.success { background: #dcfce7; color: #15803d; }
        .vc-msg.info    { background: #e0f2fe; color: #0369a1; }
        .vc-loading { text-align: center; padding: 10px; color: #64748b; font-size: 0.75rem; }
    `;
    document.head.appendChild(s);
})();

// ── Helper ──────────────────────────────────────────────────
function _vcFormatMoney(num) {
    if (isNaN(num)) return '0';
    return Number(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// ── Build the voucher widget HTML ───────────────────────────
function _vcBuildHTML(prefix) {
    return `
    <div class="vc-wrapper" id="${prefix}VcWrapper">
        <div class="vc-header" onclick="toggleVoucherCheckout('${prefix}')">
            <span class="vc-header-left">
                <i class="fas fa-ticket-alt"></i> Have a Voucher?
            </span>
            <i class="fas fa-chevron-down vc-chevron" id="${prefix}VcChevron"></i>
        </div>
        <div class="vc-body" id="${prefix}VcBody">
            <div class="vc-wallet-label"><i class="fas fa-wallet" style="margin-right:5px;"></i>Your Wallet Vouchers</div>
            <div class="vc-voucher-list" id="${prefix}VcList">
                <div class="vc-loading"><i class="fas fa-spinner fa-spin"></i> Loading vouchers...</div>
            </div>
            <div class="vc-wallet-label" style="margin-top:12px;"><i class="fas fa-keyboard" style="margin-right:5px;"></i>Or Enter Voucher Code</div>
            <div class="vc-input-row">
                <input type="text" id="${prefix}VcCodeInput" placeholder="e.g. HEYDREAM2025" maxlength="40">
                <button class="vc-apply-btn" onclick="applyVoucherByCode('${prefix}')">Apply</button>
            </div>
            <div id="${prefix}VcMsg"></div>
            <div id="${prefix}VcPriceSummary" style="display:none;"></div>
        </div>
    </div>`;
}

// ── Toggle open/close ────────────────────────────────────────
window.toggleVoucherCheckout = function(prefix) {
    const body    = document.getElementById(prefix + 'VcBody');
    const chevron = document.getElementById(prefix + 'VcChevron');
    if (!body) return;
    const isOpen = body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('open', isOpen);
    if (isOpen) _vcLoadWalletVouchers(prefix);
};

// ── Init: inject HTML before the payment methods container ──
window.initVoucherCheckout = function(prefix, totalAmount, targetType) {
    window._appliedVoucher[prefix] = null;
    window._vcTotalAmount  = window._vcTotalAmount  || {};
    window._vcRawTotalAmount  = window._vcRawTotalAmount  || {};
    window._vcTargetType   = window._vcTargetType   || {};
    window._vcTotalAmount[prefix]  = totalAmount;
    window._vcRawTotalAmount[prefix] = totalAmount;
    window._vcTargetType[prefix]   = targetType || '';

    // Inject into Step 4 (before the payment-methods div)
    const step4 = document.getElementById(prefix + 'Step4Content');
    if (!step4) return;
    if (step4.querySelector('.vc-wrapper')) return; // already injected

    // Find the h3 "Select Payment Method" inside step4
    const h3 = step4.querySelector('h3');
    if (h3) {
        h3.insertAdjacentHTML('afterend', _vcBuildHTML(prefix));
    } else {
        step4.insertAdjacentHTML('afterbegin', _vcBuildHTML(prefix));
    }
};

// ── Load user's wallet vouchers ──────────────────────────────
function _vcLoadWalletVouchers(prefix) {
    const list = document.getElementById(prefix + 'VcList');
    if (!list) return;

    const applied = window._appliedVoucher[prefix];

    function getTargetTypeByPrefix(prefix) {
        const mapping = {
            home: 'local_destinations',
            foreign: 'foreign_destinations',
            flash: 'flash_deals',
            local: 'local_destinations'
        };
        return mapping[prefix] || 'local_destinations';
    }

    fetch('api/user_voucher_api.php?action=get_my_vouchers')
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                list.innerHTML = `<div class="vc-loading"><i class="fas fa-tags" style="color:#cbd5e1;"></i> No vouchers in your wallet. <a href="index.php#voucher-center" style="color:#003580;font-weight:700;">Claim one!</a></div>`;
                return;
            }

            const usable = res.data.filter(v => parseInt(v.is_used) !== 1);
            if (usable.length === 0) {
                list.innerHTML = `<div class="vc-loading">All your vouchers have been used.</div>`;
                return;
            }

            list.innerHTML = usable.map(v => {
                const isApplied = applied && applied.id == v.id;
                const discVal   = parseFloat(v.discount_value);
                const valStr    = v.discount_type === 'percentage' ? `${discVal}%` : `₱${_vcFormatMoney(discVal)}`;
                const minSpend  = parseFloat(v.minimum_spend);
                const minStr    = minSpend > 0 ? `Min. ₱${_vcFormatMoney(minSpend)}` : 'No Min. Spend';
                const notApplicable = !isApplied && !isVoucherApplicable(v, prefix);
                return `
                <div class="vc-voucher-item ${isApplied ? 'applied' : ''} ${notApplicable ? 'disabled' : ''}" id="${prefix}VcItem${v.id}">
                    <div class="vc-vi-left">
                        <div class="vc-vi-badge">${valStr}<br><span style="font-size:0.55rem;opacity:0.85;">OFF</span></div>
                        <div class="vc-vi-info">
                            <span class="vc-vi-name">${_vcEsc(v.voucher_name)}</span>
                            <span class="vc-vi-code">${_vcEsc(v.voucher_code)}</span>
                            <span class="vc-vi-min">${minStr}</span>
                        </div>
                    </div>
                    ${isApplied
                        ? `<button class="vc-vi-btn remove" onclick="removeCheckoutVoucher('${prefix}')">Remove</button>`
                        : notApplicable
                            ? `<button class="vc-vi-btn disabled" disabled>Not available</button>`
                            : `<button class="vc-vi-btn apply"  onclick="applyCheckoutVoucherById('${prefix}', ${v.id}, '${_vcEsc(v.voucher_code)}')">Use</button>`
                    }
                </div>`;
            }).join('');
        })
        .catch(() => {
            list.innerHTML = `<div class="vc-loading">Could not load vouchers.</div>`;
        });
}

function isVoucherApplicable(voucher, prefix) {
    const targetType = window._vcTargetType && window._vcTargetType[prefix] ? window._vcTargetType[prefix] : 'local_destinations';
    const packageId = window._vcPackageId && window._vcPackageId[prefix] ? window._vcPackageId[prefix] : 0;

    // If voucher restricts target types and current type doesn't match, it's not applicable.
    if (voucher.targets && voucher.targets.length > 0 && !voucher.targets.includes(targetType)) {
        return false;
    }

    // If package-level restriction exists, enforce it.
    if (voucher.package_targets && voucher.package_targets.length > 0) {
        const matchingPackageTargets = voucher.package_targets.filter(pt => pt.target_type === targetType).map(pt => parseInt(pt.package_id, 10));
        if (matchingPackageTargets.length > 0 && packageId > 0) {
            return matchingPackageTargets.includes(packageId);
        }
    }

    // If total is below the required minimum spend, the voucher is not currently applicable.
    const currentTotal = window._vcRawTotalAmount && window._vcRawTotalAmount[prefix]
        ? parseFloat(window._vcRawTotalAmount[prefix])
        : window._vcTotalAmount && window._vcTotalAmount[prefix]
            ? parseFloat(window._vcTotalAmount[prefix])
            : 0;
    if (parseFloat(voucher.minimum_spend) > 0 && currentTotal < parseFloat(voucher.minimum_spend)) {
        return false;
    }

    // If the voucher only applies to a capped number of travelers, require at least that many travelers.
    const currentTravelers = _vcGetTravelerCount(prefix);
    const maxDiscountedTravelers = parseInt(voucher.max_discounted_travelers, 10) || 0;
    if (maxDiscountedTravelers > 0 && currentTravelers > 0 && currentTravelers < maxDiscountedTravelers) {
        return false;
    }

    // Otherwise, the voucher is applicable.
    return true;
}

// ── Apply by code (manual input) ────────────────────────────
window.applyVoucherByCode = function(prefix) {
    const input = document.getElementById(prefix + 'VcCodeInput');
    if (!input) return;
    const code = input.value.trim().toUpperCase();
    if (!code) { _vcMsg(prefix, 'Please enter a voucher code.', 'error'); return; }

    // Look up by code from wallet first
    fetch('api/user_voucher_api.php?action=get_my_vouchers')
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data) { _vcMsg(prefix, 'Could not load vouchers.', 'error'); return; }
            const match = res.data.find(v => v.voucher_code.toUpperCase() === code && parseInt(v.is_used) !== 1);
            if (!match) {
                _vcMsg(prefix, 'Voucher not found in your wallet or already used. Claim it first from the homepage.', 'error');
                return;
            }
            if (!isVoucherApplicable(match, prefix)) {
                _vcMsg(prefix, 'This voucher is not valid for the selected package.', 'error');
                return;
            }
            applyCheckoutVoucherById(prefix, match.id, match.voucher_code);
        })
        .catch(() => _vcMsg(prefix, 'Server error. Please try again.', 'error'));
};

// ── Get traveler count for prefix
function _vcGetTravelerCount(prefix) {
    const map = {
        home: 'homeTravelersCount',
        foreign: 'foreignStepTravelers',
        flash: 'flashStepTravelers',
        local: 'localStepTravelers'
    };
    const inputId = map[prefix];
    if (!inputId) return 0;
    const input = document.getElementById(inputId);
    const value = input ? parseInt(input.value) : 0;
    return value > 0 ? value : 0;
}

// ── Apply by voucher ID ──────────────────────────────────────
window.applyCheckoutVoucherById = function(prefix, voucherId, voucherCode) {
    const total = (window._vcRawTotalAmount && typeof window._vcRawTotalAmount[prefix] !== 'undefined')
        ? window._vcRawTotalAmount[prefix]
        : (window._vcTotalAmount && window._vcTotalAmount[prefix]) || 0;
    const targetType = window._vcTargetType[prefix]  || '';

    _vcMsg(prefix, '<i class="fas fa-spinner fa-spin"></i> Validating voucher...', 'info');

    const fd = new FormData();
    fd.append('action',      'validate_voucher');
    fd.append('voucher_id',  voucherId);
    fd.append('total_amount', total);
    fd.append('travelers',    _vcGetTravelerCount(prefix));
    fd.append('target_type', targetType);
    fd.append('package_id', window._vcPackageId && window._vcPackageId[prefix] ? window._vcPackageId[prefix] : 0);

    fetch('api/user_voucher_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (window._appliedVoucher[prefix] && window._appliedVoucher[prefix].id === voucherId) {
                    window._appliedVoucher[prefix] = null;
                    const summary = document.getElementById(prefix + 'VcPriceSummary');
                    if (summary) summary.style.display = 'none';
                    _vcRefreshListUI(prefix);
                }
                _vcMsg(prefix, res.message || 'Voucher cannot be applied.', 'error');
                return;
            }
            const d = res.data;
            window._appliedVoucher[prefix] = {
                id:                     d.voucher_id,
                code:                   d.voucher_code,
                name:                   d.voucher_name,
                discountAmount:         d.discount_amount,
                finalTotal:             d.final_amount,
                originalTotal:          d.original_amount,
                eligibleTravelers:      d.eligible_travelers || 0,
                maxDiscountedTravelers: d.max_discounted_travelers || 0
            };
            if (window._vcRawTotalAmount) {
                window._vcRawTotalAmount[prefix] = d.original_amount;
            }
            if (window._vcTotalAmount) {
                window._vcTotalAmount[prefix] = d.final_amount;
            }

            _vcMsg(prefix, `✅ Voucher "${d.voucher_code}" applied! You save ₱${_vcFormatMoney(d.discount_amount)}.`, 'success');
            _vcShowPriceSummary(prefix, d.original_amount, d.discount_amount, d.final_amount, d.eligible_travelers, d.max_discounted_travelers);
            _vcUpdateVoucherDisplays(prefix, d.final_amount, d.original_amount);
            _vcRefreshListUI(prefix);
        })
        .catch(() => _vcMsg(prefix, 'Server error. Please try again.', 'error'));
};

// ── Remove applied voucher ───────────────────────────────────
window.removeCheckoutVoucher = function(prefix) {
    const rawTotal = (window._vcRawTotalAmount && typeof window._vcRawTotalAmount[prefix] !== 'undefined')
        ? window._vcRawTotalAmount[prefix]
        : (window._vcTotalAmount && typeof window._vcTotalAmount[prefix] !== 'undefined')
            ? window._vcTotalAmount[prefix]
            : 0;
    window._appliedVoucher[prefix] = null;
    if (window._vcTotalAmount) window._vcTotalAmount[prefix] = rawTotal;
    if (window._vcRawTotalAmount) window._vcRawTotalAmount[prefix] = rawTotal;
    _vcMsg(prefix, '', '');
    const summary = document.getElementById(prefix + 'VcPriceSummary');
    if (summary) summary.style.display = 'none';
    _vcUpdateVoucherDisplays(prefix, rawTotal, rawTotal);
    _vcRefreshListUI(prefix);
};

// ── Refresh the wallet list UI after apply/remove ────────────
function _vcRefreshListUI(prefix) {
    _vcLoadWalletVouchers(prefix);
}

// ── Show price breakdown ─────────────────────────────────────
function _vcShowPriceSummary(prefix, original, discount, final, eligibleTravelers = 0, maxDiscountedTravelers = 0) {
    const el = document.getElementById(prefix + 'VcPriceSummary');
    if (!el) return;
    el.style.display = 'block';
    el.innerHTML = `
        <div class="vc-price-summary">
            <div class="vc-price-row"><span>Subtotal</span><span>₱${_vcFormatMoney(original)}</span></div>
            <div class="vc-price-row discount"><span>Voucher Discount</span><span>-₱${_vcFormatMoney(discount)}</span></div>
            ${maxDiscountedTravelers > 0 ? `<div class="vc-price-row"><span>Discount applies to</span><span>${eligibleTravelers} of ${maxDiscountedTravelers} traveler${eligibleTravelers === 1 ? '' : 's'}</span></div>` : ''}
            <div class="vc-price-row final"><span>Total After Discount</span><span>₱${_vcFormatMoney(final)}</span></div>
        </div>`;
}

function _vcUpdateVoucherDisplays(prefix, finalAmount, rawAmount) {
    const sym = (window._vcCurrencyFn && window._vcCurrencyFn[prefix]) ? window._vcCurrencyFn[prefix]() : '₱';
    const formattedFinal = _vcFormatMoney(finalAmount);
    const formattedRaw = _vcFormatMoney(rawAmount);
    const amountIds = [
        `${prefix}GcashAmount`,
        `${prefix}PaymayaAmount`,
        `${prefix}BankAmount`,
        `${prefix}SummaryTotal`,
        `${prefix}ReviewTotal`,
        `${prefix}ConfirmTotal`
    ];

    amountIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = `${sym}${formattedFinal}` +
            (rawAmount !== undefined && rawAmount !== null && rawAmount !== finalAmount
                ? ` <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${sym}${formattedRaw}</span>`
                : '');
    });
}

// ── Message helper ───────────────────────────────────────────
function _vcMsg(prefix, html, type) {
    const el = document.getElementById(prefix + 'VcMsg');
    if (!el) return;
    if (!html) { el.innerHTML = ''; return; }
    el.innerHTML = `<div class="vc-msg ${type}">${html}</div>`;
}

// ── XSS escape ───────────────────────────────────────────────
function _vcEsc(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, t => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[t]||t));
}
