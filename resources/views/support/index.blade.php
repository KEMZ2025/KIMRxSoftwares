<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            background:
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.09), transparent 28%),
                linear-gradient(180deg, #f8fafc 0%, #eef6ff 100%);
            color: #172033;
        }
        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            padding: 20px;
        }
        .topbar,
        .panel,
        .card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            backdrop-filter: blur(10px);
        }
        .topbar,
        .panel {
            margin-bottom: 20px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .topbar h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
        }
        .topbar p,
        .panel-subtitle,
        .card-copy,
        .meta-note,
        .list li {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.55;
        }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .chip {
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #e0f2fe, #eff8ff);
            color: #0369a1;
            font-weight: 800;
            white-space: nowrap;
            font-size: 13px;
        }
        .chip.alt {
            background: linear-gradient(135deg, #ecfdf3, #f0fdf4);
            color: #067647;
        }
        .contact-grid,
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .panel h2,
        .card h3 {
            margin: 0 0 8px;
        }
        .panel h2 {
            font-size: 22px;
        }
        .card h3 {
            font-size: 19px;
        }
        .card {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 100%;
        }
        .card-label {
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .contact-value {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
            color: #102a43;
            overflow-wrap: anywhere;
        }
        .contact-value.muted {
            color: #98a2b3;
            font-size: 20px;
        }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: auto;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0f8a94, #0c6d75);
            color: #fff;
        }
        .btn-secondary {
            background: #fff;
            color: #344054;
            border-color: #d0d5dd;
        }
        .list {
            margin: 0;
            padding-left: 18px;
        }
        .workspace-card {
            border-radius: 18px;
            padding: 16px 18px;
            background: linear-gradient(180deg, #f8fafc, #eef6ff);
            border: 1px solid #dbe7f3;
        }
        .workspace-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            color: #0f172a;
        }
        .workspace-card span {
            display: block;
            color: #475467;
            font-size: 14px;
            line-height: 1.5;
        }
        .empty-note {
            border-radius: 16px;
            padding: 14px 16px;
            background: #fffaeb;
            border: 1px solid #fedf89;
            color: #92400e;
            font-size: 13px;
            font-weight: 700;
        }
        @media (max-width: 900px) {
            .topbar {
                flex-direction: column;
            }
            .chips {
                justify-content: flex-start;
            }
            .contact-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <div>
                <p class="eyebrow">Support Desk</p>
                <h1>{{ $support['company_name'] }}</h1>
                <p>Use this screen whenever the pharmacy team needs help with errors, setup guidance, printing, reports, or account questions.</p>
            </div>

            <div class="chips">
                <span class="chip">{{ $clientName }}</span>
                <span class="chip alt">{{ $branchName }}</span>
                <span class="chip">User: {{ $user->name }}</span>
            </div>
        </section>

        <section class="panel">
            <h2>Reach Support Quickly</h2>
            <p class="panel-subtitle">Choose the contact method that works best for the issue. Phone and WhatsApp are fastest for urgent operational blockers.</p>

            <div class="contact-grid">
                <article class="card">
                    <div class="card-label">Primary Phone</div>
                    <div class="contact-value {{ $support['phone_primary'] ? '' : 'muted' }}">{{ $support['phone_primary'] ?? 'Not yet configured' }}</div>
                    <p class="card-copy">Best for urgent till, stock, printing, login, or sales workflow issues.</p>
                    <div class="action-row">
                        @if ($support['tel_primary'])
                            <a href="{{ $support['tel_primary'] }}" class="btn btn-primary">Call Support</a>
                        @else
                            <span class="btn btn-secondary">Contact not set</span>
                        @endif
                    </div>
                </article>

                <article class="card">
                    <div class="card-label">Support Email</div>
                    <div class="contact-value {{ $support['email'] ? '' : 'muted' }}">{{ $support['email'] ?? 'Not yet configured' }}</div>
                    <p class="card-copy">Best for sending screenshots, issue summaries, client requests, or follow-up notes.</p>
                    <div class="action-row">
                        @if ($support['mailto'])
                            <a href="{{ $support['mailto'] }}" class="btn btn-primary">Send Email</a>
                        @else
                            <span class="btn btn-secondary">Email not set</span>
                        @endif
                    </div>
                </article>

                <article class="card">
                    <div class="card-label">WhatsApp</div>
                    <div class="contact-value {{ $support['whatsapp'] ? '' : 'muted' }}">{{ $support['whatsapp'] ?? 'Not yet configured' }}</div>
                    <p class="card-copy">Useful when the branch needs quick back-and-forth guidance during live operations.</p>
                    <div class="action-row">
                        @if ($support['whatsapp_url'])
                            <a href="{{ $support['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Open WhatsApp</a>
                        @else
                            <span class="btn btn-secondary">WhatsApp not set</span>
                        @endif
                    </div>
                </article>

                <article class="card">
                    <div class="card-label">Support Hours</div>
                    <div class="contact-value">{{ $support['hours'] }}</div>
                    <p class="card-copy">{{ $support['response_note'] }}</p>
                    <div class="action-row">
                        @if ($support['phone_secondary'])
                            <a href="{{ $support['tel_secondary'] }}" class="btn btn-secondary">Call Backup Contact</a>
                        @elseif ($support['website'])
                            <a href="{{ $support['website'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Visit Website</a>
                        @else
                            <span class="btn btn-secondary">No extra contact yet</span>
                        @endif
                    </div>
                </article>
            </div>

            @if (!$support['phone_primary'] && !$support['email'] && !$support['whatsapp'])
                <div class="empty-note" style="margin-top: 18px;">
                    Support contacts have not been configured yet. Add the support phone, email, and WhatsApp in the server environment so every client can see the correct KIM Retail contact details here.
                </div>
            @endif
        </section>

        <section class="detail-grid">
            <article class="panel">
                <h2>What To Share With Support</h2>
                <p class="panel-subtitle">These details help KIM Retail Software Systems solve issues faster and avoid back-and-forth.</p>
                <ul class="list">
                    <li>The exact screen or module where the issue happened.</li>
                    <li>The action you were trying to complete before the problem appeared.</li>
                    <li>The branch, user name, and time the issue happened.</li>
                    <li>The error message or a screenshot if one appears on screen.</li>
                    <li>Whether the issue blocks selling, printing, stock, reports, or accounting.</li>
                </ul>
            </article>

            <article class="panel">
                <h2>Your Current Support Reference</h2>
                <p class="panel-subtitle">Share this context with support so they can identify the right workspace quickly.</p>

                <div class="workspace-card">
                    <strong>Client</strong>
                    <span>{{ $clientName }}</span>
                </div>

                <div class="workspace-card" style="margin-top: 14px;">
                    <strong>Branch</strong>
                    <span>{{ $branchName }}</span>
                </div>

                <div class="workspace-card" style="margin-top: 14px;">
                    <strong>Logged In User</strong>
                    <span>{{ $user->name }}</span>
                    <span>{{ $user->email }}</span>
                </div>

                @if ($support['contact_person'] || $support['website'])
                    <div class="workspace-card" style="margin-top: 14px;">
                        <strong>KIM Retail Contact Desk</strong>
                        @if ($support['contact_person'])
                            <span>{{ $support['contact_person'] }}</span>
                        @endif
                        @if ($support['website'])
                            <span><a href="{{ $support['website'] }}" target="_blank" rel="noopener noreferrer">{{ $support['website'] }}</a></span>
                        @endif
                    </div>
                @endif
            </article>
        </section>
    </main>
</body>
</html>
