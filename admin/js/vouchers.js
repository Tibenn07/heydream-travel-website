// File: admin/js/vouchers.js

let allVouchers = [];
let loadedPackagesCache = {}; // Cache to avoid multiple redundant requests for packages

document.addEventListener('DOMContentLoaded', () => {
    // If the page was loaded with #vouchers, fetch vouchers
    if (window.location.hash === '#vouchers') {
        fetchVouchers();
    }
});

// Fetch all vouchers from API
function fetchVouchers() {
    const tbody = document.getElementById('vouchers-list-tbody');
    if (!tbody) return;

    // Show loading state
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px; color: #003580;"></i>
                <p>Loading vouchers list...</p>
            </td>
        </tr>
    `;

    fetch('api/voucher_api.php?action=get_vouchers')
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                allVouchers = res.data;
                renderVouchersList();
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #ef4444;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Failed to load vouchers: ${res.message}</p>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            console.error('Error fetching vouchers:', err);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px; color: #ef4444;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Error connecting to the server.</p>
                    </td>
                </tr>
            `;
        });
}

// Render list with client-side filtering
function renderVouchersList() {
    const tbody = document.getElementById('vouchers-list-tbody');
    if (!tbody) return;

    const searchVal = document.getElementById('voucher-search').value.toLowerCase().trim();
    const statusVal = document.getElementById('voucher-status-filter').value;
    const audienceVal = document.getElementById('voucher-audience-filter').value;

    const filtered = allVouchers.filter(v => {
        // Search match
        const matchesSearch = v.voucher_name.toLowerCase().includes(searchVal) || 
                              v.voucher_code.toLowerCase().includes(searchVal);
        
        // Status match
        const matchesStatus = statusVal === 'all' || v.status === statusVal;

        // Audience match
        const matchesAudience = audienceVal === 'all' || v.audience === audienceVal;

        return matchesSearch && matchesStatus && matchesAudience;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-ticket-alt" style="font-size: 2.5rem; margin-bottom: 12px; color: #cbd5e1;"></i>
                    <p style="font-weight: 600; margin: 0;">No vouchers found</p>
                    <p style="font-size: 0.8rem; color: #94a3b8; margin: 4px 0 0;">Try adjusting your search query or filters.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filtered.map(v => {
        // Format discount
        const usesForeignCurrency = Array.isArray(v.targets) && v.targets.length > 0 && v.targets.every(t => t === 'foreign_destinations');
        const hasForeignAndOther = Array.isArray(v.targets) && v.targets.includes('foreign_destinations') && v.targets.some(t => t !== 'foreign_destinations');
        const currencyLabel = usesForeignCurrency ? 'USD' : hasForeignAndOther ? 'PHP / USD' : 'PHP';

        const discountText = v.discount_type === 'percentage' 
            ? `<span style="font-weight: 700; color: #003580; font-size: 1.05rem;">${parseFloat(v.discount_value)}%</span>` 
            : `<span style="font-weight: 700; color: #003580; font-size: 1.05rem;">${currencyLabel} ${parseFloat(v.discount_value).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>`;

        // Format min spend and max discount details
        let detailsText = `<div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">Min spend: ${currencyLabel} ${parseFloat(v.minimum_spend).toLocaleString()}</div>`;
        if (v.discount_type === 'percentage' && v.maximum_discount) {
            detailsText += `<div style="font-size: 0.78rem; color: #059669; margin-top: 2px;">Max Discount: ${currencyLabel} ${parseFloat(v.maximum_discount).toLocaleString()}</div>`;
        }

        // Format target modules
        let targetsBadges = '';
        if (v.targets && v.targets.length > 0) {
            targetsBadges = v.targets.map(t => {
                const label = t.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                return `<span style="display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 600; padding: 3px 8px; border-radius: 6px; margin: 2px;">${label}</span>`;
            }).join('');

            // If there are specific packages
            if (v.packages && v.packages.length > 0) {
                targetsBadges += `<div style="font-size: 0.7rem; color: #475569; font-weight: 600; margin-top: 4px;"><i class="fas fa-info-circle"></i> Targeted to ${v.packages.length} specific package(s)</div>`;
            }
        } else {
            targetsBadges = `<span style="display: inline-block; background: #f1f5f9; color: #475569; font-size: 0.72rem; font-weight: 600; padding: 3px 8px; border-radius: 6px;">Site-wide</span>`;
        }

        // Redemptions limit
        const limitText = parseInt(v.max_total_redemptions) > 0 ? v.max_total_redemptions : 'Unlimited';
        const redemptionInfo = `
            <div style="font-weight: 600; color: #1e293b;">${v.redemption_count} used</div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">Limit: ${limitText}</div>
        `;

        // Date validity
        const startDate = new Date(v.start_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
        const endDate = new Date(v.end_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
        const validityStatusHtml = new Date(v.end_date) < new Date() 
            ? `<div style="font-size: 0.72rem; color: #ef4444; font-weight: 700; margin-top: 4px;"><i class="fas fa-exclamation-circle"></i> Expired</div>`
            : '';

        // Status Badge
        const statusBadge = v.status === 'active'
            ? `<span style="display: inline-flex; align-items: center; gap: 5px; background: #d1fae5; color: #065f46; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #059669;"></span> Active</span>`
            : `<span style="display: inline-flex; align-items: center; gap: 5px; background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Inactive</span>`;

        // Audience & Collection Details
        const audienceLabel = v.audience.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const collectionLabel = v.collection_method === 'auto_available' ? 'Auto-applied' : 'Claimable';
        const audienceInfo = `
            <div style="font-weight: 600; color: #1e293b; font-size: 0.82rem;">${audienceLabel}</div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">Method: ${collectionLabel}</div>
        `;

        return `
            <tr style="border-bottom: 1px solid #f1f5f9; hover: background: #f8fafc;">
                <td style="padding: 16px 20px; vertical-align: top;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 42px; height: 42px; border-radius: 10px; background: ${v.color_theme || '#003580'}; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;">${v.voucher_name}</div>
                            <div style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-top: 2px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block;">${v.voucher_code}</div>
                        </div>
                    </div>
                </td>
                <td style="padding: 16px 20px; vertical-align: top;">
                    ${discountText}
                    ${detailsText}
                </td>
                <td style="padding: 16px 20px; vertical-align: top; max-width: 200px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                        ${targetsBadges}
                    </div>
                </td>
                <td style="padding: 16px 20px; vertical-align: top;">
                    ${redemptionInfo}
                </td>
                <td style="padding: 16px 20px; vertical-align: top;">
                    <div style="font-size: 0.8rem; color: #334155;">${startDate} - ${endDate}</div>
                    ${validityStatusHtml}
                </td>
                <td style="padding: 16px 20px; vertical-align: top;">
                    ${statusBadge}
                </td>
                <td style="padding: 16px 20px; text-align: right; vertical-align: top;">
                    <div style="display: inline-flex; gap: 6px;">
                        <button onclick="openEditVoucherModal(${v.id})" class="btn btn-outline" style="padding: 6px 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; color: #475569; background: white; cursor: pointer; transition: all 0.2s;" title="Edit Voucher">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="duplicateVoucher(${v.id})" class="btn btn-outline" style="padding: 6px 10px; border-radius: 8px; border: 1.5px solid #cbd5e1; color: #0369a1; background: white; cursor: pointer; transition: all 0.2s;" title="Duplicate Voucher">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button onclick="deleteVoucher(${v.id})" class="btn btn-outline" style="padding: 6px 10px; border-radius: 8px; border: 1.5px solid #fecaca; color: #dc2626; background: white; cursor: pointer; transition: all 0.2s;" title="Delete Voucher">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Filter triggers
function filterVouchers() {
    renderVouchersList();
}

// Show the modal to add a new voucher
function openAddVoucherModal() {
    const form = document.getElementById('voucher-form');
    if (!form) return;

    form.reset();
    document.getElementById('voucher-id-input').value = '';
    document.getElementById('voucher-modal-title').innerHTML = `<i class="fas fa-ticket-alt" style="color: #003580; margin-right: 8px;"></i> Create New Voucher`;
    
    // Clear targets checkmarks
    const checkboxes = form.querySelectorAll('input[name="targets[]"]');
    checkboxes.forEach(cb => cb.checked = false);

    // Hide specific packages targeting
    document.getElementById('package-targeting-container').style.display = 'none';
    document.getElementById('target-packages-list').innerHTML = '';

    toggleDiscountFields();
    
    document.getElementById('voucher-form-modal').style.display = 'flex';
}

// Show the modal to edit an existing voucher
function openEditVoucherModal(id) {
    Swal.fire({
        title: 'Loading voucher details...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`api/voucher_api.php?action=get_voucher&id=${id}`)
        .then(response => response.json())
        .then(res => {
            Swal.close();
            if (res.success) {
                const v = res.data;
                document.getElementById('voucher-id-input').value = v.id;
                document.getElementById('v-name').value = v.voucher_name;
                document.getElementById('v-code').value = v.voucher_code;
                document.getElementById('v-desc').value = v.description;
                document.getElementById('v-discount-type').value = v.discount_type;
                document.getElementById('v-discount-value').value = v.discount_value;
                document.getElementById('v-min-spend').value = v.minimum_spend;
                document.getElementById('v-max-discount').value = v.maximum_discount || '';
                document.getElementById('v-max-total').value = v.max_total_redemptions;
                document.getElementById('v-max-user').value = v.max_redemptions_per_user;
                document.getElementById('v-start-date').value = v.start_date;
                document.getElementById('v-end-date').value = v.end_date;
                document.getElementById('v-audience').value = v.audience;
                document.getElementById('v-collection-method').value = v.collection_method;
                document.getElementById('v-status').value = v.status;
                document.getElementById('v-color').value = v.color_theme || '#003580';

                // Check targets
                const checkboxes = document.querySelectorAll('input[name="targets[]"]');
                checkboxes.forEach(cb => {
                    cb.checked = v.targets.includes(cb.value);
                });

                document.getElementById('voucher-modal-title').innerHTML = `<i class="fas fa-edit" style="color: #003580; margin-right: 8px;"></i> Edit Voucher: ${v.voucher_code}`;
                
                // Show modal
                document.getElementById('voucher-form-modal').style.display = 'flex';
                toggleDiscountFields();

                // Trigger package targeting loading
                loadTargetPackages(() => {
                    // Once target packages are loaded, check the previously targeted packages
                    if (v.packages && v.packages.length > 0) {
                        v.packages.forEach(pkg => {
                            const cb = document.querySelector(`input[name="packages[]"][value*='"target_type":"${pkg.target_type}"'][value*='"package_id":"${pkg.package_id}"']`);
                            if (cb) cb.checked = true;
                        });
                    }
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        })
        .catch(err => {
            console.error('Error fetching details:', err);
            Swal.fire('Error', 'Unable to retrieve voucher details.', 'error');
        });
}

// Close the modal
function closeVoucherModal() {
    document.getElementById('voucher-form-modal').style.display = 'none';
}

// Toggle Max Discount field visibility based on Discount Type
function toggleDiscountFields() {
    const type = document.getElementById('v-discount-type').value;
    const maxDiscountWrapper = document.getElementById('max-discount-wrapper');
    if (type === 'percentage') {
        maxDiscountWrapper.style.opacity = '1';
        maxDiscountWrapper.style.pointerEvents = 'auto';
        document.getElementById('v-max-discount').disabled = false;
    } else {
        maxDiscountWrapper.style.opacity = '0.5';
        maxDiscountWrapper.style.pointerEvents = 'none';
        document.getElementById('v-max-discount').value = '';
        document.getElementById('v-max-discount').disabled = true;
    }
}

// Load packages for all checked target sections
function loadTargetPackages(callback = null) {
    const checkedTargets = Array.from(document.querySelectorAll('input[name="targets[]"]:checked')).map(cb => cb.value);
    const container = document.getElementById('package-targeting-container');
    const list = document.getElementById('target-packages-list');

    if (checkedTargets.length === 0) {
        container.style.display = 'none';
        list.innerHTML = '';
        if (typeof callback === 'function') callback();
        return;
    }

    container.style.display = 'block';
    list.innerHTML = `<div style="text-align: center; color: #94a3b8; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Fetching packages...</div>`;

    // Fetch packages for all targets in parallel
    const fetchPromises = checkedTargets.map(targetType => {
        if (loadedPackagesCache[targetType]) {
            return Promise.resolve({ target_type: targetType, data: loadedPackagesCache[targetType] });
        }
        return fetch(`api/voucher_api.php?action=get_packages_for_target&target_type=${targetType}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    loadedPackagesCache[targetType] = res.data;
                    return { target_type: targetType, data: res.data };
                }
                return { target_type: targetType, data: [] };
            })
            .catch(() => ({ target_type: targetType, data: [] }));
    });

    Promise.all(fetchPromises).then(results => {
        list.innerHTML = '';
        let totalPackages = 0;

        results.forEach(res => {
            if (res.data && res.data.length > 0) {
                const targetLabel = res.target_type.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                
                // Add header group label
                const groupHeader = document.createElement('div');
                groupHeader.style.fontWeight = '700';
                groupHeader.style.fontSize = '0.75rem';
                groupHeader.style.color = '#003580';
                groupHeader.style.marginTop = '6px';
                groupHeader.style.marginBottom = '4px';
                groupHeader.style.borderBottom = '1px solid #f1f5f9';
                groupHeader.style.paddingBottom = '2px';
                groupHeader.innerText = targetLabel;
                list.appendChild(groupHeader);

                res.data.forEach(item => {
                    totalPackages++;
                    const itemLabel = document.createElement('label');
                    itemLabel.style.display = 'flex';
                    itemLabel.style.alignItems = 'center';
                    itemLabel.style.gap = '8px';
                    itemLabel.style.fontSize = '0.8rem';
                    itemLabel.style.color = '#334155';
                    itemLabel.style.cursor = 'pointer';
                    itemLabel.style.padding = '2px 0';

                    // Value stores the JSON string mapping target_type and package_id
                    const valueStr = JSON.stringify({ target_type: res.target_type, package_id: item.id });
                    
                    itemLabel.innerHTML = `
                        <input type="checkbox" name="packages[]" value='${valueStr}' style="accent-color: #003580;">
                        <span>${item.name} <span style="color: #94a3b8; font-size: 0.72rem;">(ID: ${item.id})</span></span>
                    `;
                    list.appendChild(itemLabel);
                });
            }
        });

        if (totalPackages === 0) {
            list.innerHTML = `<div style="text-align: center; color: #94a3b8; padding: 10px; font-size: 0.8rem;">No specific packages found for selected sections. All packages will be targeted.</div>`;
        }

        if (typeof callback === 'function') callback();
    });
}

// Handle Form Submission
function saveVoucherForm(event) {
    event.preventDefault();

    const form = document.getElementById('voucher-form');
    const formData = new FormData(form);
    
    // Append action
    formData.append('action', 'save_voucher');

    // Add selected targets checklist manually to guarantee clean arrays
    const checkedTargets = Array.from(document.querySelectorAll('input[name="targets[]"]:checked')).map(cb => cb.value);
    formData.delete('targets[]');
    checkedTargets.forEach(target => {
        formData.append('targets[]', target);
    });

    // Add selected packages checklist
    const checkedPackages = Array.from(document.querySelectorAll('input[name="packages[]"]:checked')).map(cb => cb.value);
    formData.delete('packages[]');
    checkedPackages.forEach(pkg => {
        formData.append('packages[]', pkg);
    });

    Swal.fire({
        title: 'Saving voucher...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('api/voucher_api.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                Swal.fire('Success', res.message, 'success');
                closeVoucherModal();
                fetchVouchers();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        })
        .catch(err => {
            console.error('Error saving voucher:', err);
            Swal.fire('Error', 'An error occurred while saving the voucher.', 'error');
        });
}

// Delete a voucher
function deleteVoucher(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! All user collected wallets & redemptions reference for this voucher will be updated or deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const bodyData = new FormData();
            bodyData.append('action', 'delete_voucher');
            bodyData.append('id', id);

            fetch('api/voucher_api.php', {
                method: 'POST',
                body: bodyData
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Deleted!', res.message, 'success');
                        fetchVouchers();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error('Error deleting voucher:', err);
                    Swal.fire('Error', 'Unable to complete deletion request.', 'error');
                });
        }
    });
}

// Duplicate a voucher
function duplicateVoucher(id) {
    Swal.fire({
        title: 'Duplicate Voucher',
        text: "Would you like to duplicate this voucher's rules?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#003580',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, duplicate'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Duplicating...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const bodyData = new FormData();
            bodyData.append('action', 'duplicate_voucher');
            bodyData.append('id', id);

            fetch('api/voucher_api.php', {
                method: 'POST',
                body: bodyData
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Duplicated!', res.message, 'success');
                        fetchVouchers();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error('Error duplicating voucher:', err);
                    Swal.fire('Error', 'Unable to complete duplication request.', 'error');
                });
        }
    });
}
