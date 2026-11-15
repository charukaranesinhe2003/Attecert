<section class="page-hero">
    <div class="container">
        <h1>Bank Details & Payment Information</h1>
        <p class="lead">Official bank accounts for AtteCert document attestation, translation, and legalization services. Please use your Case Number or Invoice Number as the transfer reference.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <style>
            .bank-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2.5rem;
            }
            .bank-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .bank-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            }
            .bank-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 2px solid #f1f5f9;
                padding-bottom: 0.85rem;
                margin-bottom: 1.15rem;
            }
            .bank-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: #092c4c;
                margin: 0;
            }
            .bank-badge {
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                padding: 0.25rem 0.6rem;
                border-radius: 20px;
                background: #e0f2fe;
                color: #0284c7;
            }
            .bank-details-list {
                list-style: none;
                padding: 0;
                margin: 0 0 1.25rem 0;
            }
            .bank-details-list li {
                margin-bottom: 0.75rem;
                display: flex;
                flex-direction: column;
            }
            .bank-label {
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #64748b;
                margin-bottom: 0.15rem;
            }
            .bank-val {
                font-size: 1.05rem;
                font-weight: 600;
                color: #1e293b;
            }
            .acc-number-row {
                background: #f8fafc;
                border: 1px dashed #cbd5e1;
                border-radius: 8px;
                padding: 0.6rem 0.85rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 0.25rem;
            }
            .acc-number-text {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 1.2rem;
                font-weight: 700;
                color: #0284c7;
                letter-spacing: 0.05em;
            }
            .btn-copy {
                background: #092c4c;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                padding: 0.35rem 0.7rem;
                font-size: 0.8rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }
            .btn-copy:hover {
                background: #0284c7;
            }
            .instruction-box {
                background: #f8fafc;
                border-left: 4px solid #0284c7;
                border-radius: 8px;
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
            .instruction-box h3 {
                margin-top: 0;
                color: #092c4c;
                font-size: 1.15rem;
            }
            .instruction-steps {
                padding-left: 1.25rem;
                margin: 0;
                color: #334155;
            }
            .instruction-steps li {
                margin-bottom: 0.5rem;
                line-height: 1.6;
            }
            .support-callout {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                border-radius: 8px;
                padding: 1.25rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 1rem;
            }
        </style>

        <div class="bank-cards-grid">
            <!-- BOC -->
            <div class="bank-card">
                <div>
                    <div class="bank-card-header">
                        <h2 class="bank-title">Bank of Ceylon</h2>
                        <span class="bank-badge">BOC</span>
                    </div>
                    <ul class="bank-details-list">
                        <li>
                            <span class="bank-label">Account Name</span>
                            <span class="bank-val">K.A. Mohammed</span>
                        </li>
                        <li>
                            <span class="bank-label">Branch</span>
                            <span class="bank-val">Wellawatha Branch</span>
                        </li>
                        <li>
                            <span class="bank-label">Account Number</span>
                            <div class="acc-number-row">
                                <span class="acc-number-text" id="acc-boc">3806066</span>
                                <button type="button" class="btn-copy" onclick="copyAccount('acc-boc', this)">Copy</button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- HNB -->
            <div class="bank-card">
                <div>
                    <div class="bank-card-header">
                        <h2 class="bank-title">Hatton National Bank</h2>
                        <span class="bank-badge">HNB</span>
                    </div>
                    <ul class="bank-details-list">
                        <li>
                            <span class="bank-label">Account Name</span>
                            <span class="bank-val">K.A. Mohammed</span>
                        </li>
                        <li>
                            <span class="bank-label">Branch</span>
                            <span class="bank-val">Wellawatha Branch</span>
                        </li>
                        <li>
                            <span class="bank-label">Account Number</span>
                            <div class="acc-number-row">
                                <span class="acc-number-text" id="acc-hnb">009020835573</span>
                                <button type="button" class="btn-copy" onclick="copyAccount('acc-hnb', this)">Copy</button>
                            </div>
                        </li>
                        <li style="margin-top: 0.5rem;">
                            <span class="bank-label">Swift Code (International Wire)</span>
                            <div class="acc-number-row" style="background: #fffbeb; border-color: #fde68a;">
                                <span class="acc-number-text" id="swift-hnb" style="color: #b45309; font-size: 1rem;">HBLILKLX</span>
                                <button type="button" class="btn-copy" style="background: #b45309;" onclick="copyAccount('swift-hnb', this)">Copy</button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Commercial Bank -->
            <div class="bank-card">
                <div>
                    <div class="bank-card-header">
                        <h2 class="bank-title">Commercial Bank</h2>
                        <span class="bank-badge">Combank</span>
                    </div>
                    <ul class="bank-details-list">
                        <li>
                            <span class="bank-label">Account Name</span>
                            <span class="bank-val">K.A. Mohammed</span>
                        </li>
                        <li>
                            <span class="bank-label">Branch</span>
                            <span class="bank-val">Wellawatha Branch</span>
                        </li>
                        <li>
                            <span class="bank-label">Account Number</span>
                            <div class="acc-number-row">
                                <span class="acc-number-text" id="acc-combank">8110912841</span>
                                <button type="button" class="btn-copy" onclick="copyAccount('acc-combank', this)">Copy</button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="instruction-box">
            <h3>Payment Guidelines & Reference Instructions</h3>
            <ol class="instruction-steps">
                <li><strong>Payment Reference:</strong> In the payment description or remarks, please enter your <strong>Case Number</strong> (e.g., <code>ATT-2026-000123</code>) or your <strong>Invoice / Quotation Number</strong> so we can instantly identify your payment.</li>
                <li><strong>Payment Confirmation:</strong> After completing the transfer or deposit, please share a screenshot or photo of the payment receipt via WhatsApp at <strong>+94 718885769</strong> or email to <strong>info@attecert.lk</strong>.</li>
                <li><strong>Official Receipt:</strong> Once the transfer is confirmed by our accounts desk, an official digital receipt with an SMS and email notification will be generated and added to your case record.</li>
            </ol>
        </div>

        <div class="support-callout">
            <div>
                <strong style="color: #1e3a8a; font-size: 1.05rem;">Need payment assistance or have a billing query?</strong>
                <p style="margin: 0.25rem 0 0 0; color: #3b82f6;">Our team is ready to help you Monday to Friday, 9:00 to 17:00.</p>
            </div>
            <div>
                <a href="<?= e(url('/contact')) ?>" class="btn btn-primary" style="margin-right: 0.5rem;">Contact Support</a>
                <a href="https://wa.me/94718885769" target="_blank" rel="noopener noreferrer" class="btn" style="background:#25D366; color:white;">WhatsApp Us</a>
            </div>
        </div>
    </div>
</section>

<script>
function copyAccount(elementId, btn) {
    var text = document.getElementById(elementId).innerText.trim();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            var oldText = btn.innerText;
            btn.innerText = 'Copied!';
            btn.style.background = '#16a34a';
            setTimeout(function() {
                btn.innerText = oldText;
                btn.style.background = '';
            }, 2000);
        });
    } else {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        btn.innerText = 'Copied!';
        setTimeout(function() { btn.innerText = 'Copy'; }, 2000);
    }
}
</script>

