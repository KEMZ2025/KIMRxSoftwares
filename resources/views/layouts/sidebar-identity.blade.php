<div
    class="sidebar-identity"
    data-session-identity
    data-tooltip="Signed in: {{ $authUser->name }}"
    aria-label="Signed in as {{ $authUser->name }}, {{ $sessionRoleLabel }}"
>
    <span class="sidebar-identity-avatar" aria-hidden="true">{{ $sessionInitials ?: '?' }}</span>
    <span class="sidebar-identity-short" aria-hidden="true">{{ $sessionShortName }}</span>
    <span class="sidebar-identity-copy">
        <small>Signed in as</small>
        <strong>{{ $authUser->name }}</strong>
        <span>{{ $sessionRoleLabel }}</span>
    </span>
</div>
