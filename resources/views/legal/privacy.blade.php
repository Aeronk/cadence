@extends('legal.layout')

@section('title', 'Privacy Policy')
@section('description', 'How ' . config('legal.product') . ' collects, uses, stores and shares your data, including data from Google and Microsoft accounts you connect.')

@section('body')

<p>
    This policy explains what {{ config('legal.product') }} ("we", "us") collects, why, how long we keep
    it, and who else sees it. It covers the website, the application and the
    background jobs that sync your connected accounts.
</p>

<h2 id="summary">The short version</h2>
<ul>
    <li>We store the work you put into {{ config('legal.product') }}: projects, tasks, notes, meetings, goals and files.</li>
    <li>If you connect Google or Microsoft, we sync calendar events and email <em>headers</em> so they appear alongside that work.</li>
    <li><strong>We never read the body of your email.</strong> For Gmail we request message metadata only.</li>
    <li>We do not sell your data, show you advertising, or use your data to train AI models.</li>
    <li>You can disconnect an account or delete your workspace at any time, and ask us to erase what is left.</li>
</ul>

<h2 id="what-we-collect">What we collect</h2>

<h3>Account information</h3>
<p>
    Your name, email address, a hashed password (never the password itself), and —
    if you enable them — two-factor authentication secrets and passkey credentials.
</p>

<h3>Content you create</h3>
<p>
    Workspaces, projects, tasks, subtasks, to-dos, notes, comments, milestones,
    goals, clients, meetings, trips, reminders, uploaded files, and personal dates
    you choose to record such as birthdays and anniversaries. We also keep an
    activity log of changes within a workspace so your team can see what happened.
</p>

<h3>Data from accounts you connect</h3>
<p>
    Connecting an account is optional and always begins with you granting consent
    on Google's or Microsoft's own screen. You can revoke it at any time. What we
    then access is limited to the following.
</p>

<table>
    <thead>
        <tr><th>Source</th><th>What we access</th><th>Why</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Google / Microsoft profile</td>
            <td>Your account identifier and email address</td>
            <td>To attach the connection to the right person and show you which account is linked</td>
        </tr>
        <tr>
            <td>Calendar</td>
            <td>Events on the calendars <em>you choose to sync</em>: title, description, location, start and end times, recurrence, organiser, attendee addresses and RSVP status, conferencing links</td>
            <td>To show your real schedule inside {{ config('legal.product') }}, and to create, update and cancel meetings you schedule here</td>
        </tr>
        <tr>
            <td>Email (Gmail)</td>
            <td><strong>Metadata only</strong> — the From, To, Subject and Date headers, plus the short preview snippet the provider returns. We request Gmail in <code>metadata</code> format and therefore <strong>cannot</strong> retrieve message bodies or attachments.</td>
            <td>To list recent correspondence against the relevant work, and to send mail you compose here at your explicit instruction</td>
        </tr>
        <tr>
            <td>Email (Microsoft)</td>
            <td>Sender, recipients, subject, received time and the provider's short body preview</td>
            <td>Same as above</td>
        </tr>
        <tr>
            <td>SMS / WhatsApp</td>
            <td>Phone numbers and the content of messages sent or received through the service</td>
            <td>To deliver notifications and conversations you have opted into</td>
        </tr>
    </tbody>
</table>

<p>
    You choose which calendars sync. Calendars you leave unticked are never read,
    and their events never reach our servers.
</p>

<h3>Technical data</h3>
<p>
    Session cookies to keep you signed in, and server logs containing IP address,
    browser user agent and timestamps. We record deliveries of incoming provider
    notifications (webhooks) so failures can be diagnosed.
</p>

<h2 id="google-limited-use">Google user data and the Limited Use requirements</h2>

<div class="callout">
    <p>
        {{ config('legal.product') }}'s use and transfer of information received from Google APIs to any
        other app adheres to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener" target="_blank">Google API Services User Data Policy</a>, including the Limited Use requirements.
    </p>
</div>

<p>Specifically, and without exception:</p>
<ul>
    <li>We use Google user data only to provide and improve the features described above, which you can see operating in the product.</li>
    <li>We do not transfer Google user data to third parties except as necessary to provide those features, to comply with applicable law, or as part of a merger or acquisition (in which case we will require the successor to honour this policy).</li>
    <li><strong>We do not use Google user data to develop, improve or train generalised or non-personalised AI or machine-learning models.</strong></li>
    <li>We do not sell Google user data, and we do not use it for advertising of any kind.</li>
    <li>We allow humans to read Google user data only where you have given us explicit permission to do so, where it is necessary for security purposes such as investigating abuse, to comply with applicable law, or where the data has been aggregated and de-identified.</li>
</ul>

<h3>About our AI features</h3>
<p>
    {{ config('legal.product') }} can generate a daily briefing and extract action items from meeting
    notes. These features send information to our AI processor. The information
    sent is limited to content created inside {{ config('legal.product') }} — task titles, meeting titles
    and times, trip destinations, and meeting notes you type. <strong>No data
    obtained from Google or Microsoft APIs is sent to our AI processor</strong>,
    and no data of any kind is used to train models.
</p>

<h2 id="how-we-store">How we store and protect it</h2>
<ul>
    <li>OAuth access and refresh tokens are <strong>encrypted at rest</strong> in our database.</li>
    <li>Passwords are stored only as salted one-way hashes.</li>
    <li>Traffic is served over HTTPS.</li>
    <li>Incoming provider notifications are authenticated with a per-channel secret, so a third party cannot trigger activity on your account.</li>
    <li>Workspace content is readable only by members of that workspace; goals and personal dates are visible only to the person who created them.</li>
</ul>
<p>
    No system is perfectly secure. If we become aware of a breach affecting your
    personal data we will notify you and any relevant regulator as required by law.
</p>

<h2 id="sharing">Who else sees your data</h2>
<p>We do not sell your data. We share it only with processors that make the service work:</p>
<ul>
    <li><strong>Google LLC</strong> and <strong>Microsoft Corporation</strong> — for the calendar and email connections you enable.</li>
    <li><strong>Twilio</strong> and <strong>Meta Platforms</strong> — only if you enable SMS or WhatsApp messaging.</li>
    <li><strong>Our AI processor</strong> — only the {{ config('legal.product') }}-native content described above.</li>
    <li><strong>Our hosting provider</strong> — which stores the database and files on our behalf.</li>
</ul>
<p>We may also disclose data where we are legally required to, or to establish or defend legal claims.</p>

<h2 id="retention">Retention and deletion</h2>
<ul>
    <li>
        <strong>Disconnecting a Google or Microsoft account</strong> revokes our
        access and immediately deletes the synced calendar list and every calendar
        event we hold from it. Email records that were already synced are detached
        from the connection and retained as part of your workspace history; email
        you ask us to erase, we erase.
    </li>
    <li>
        <strong>Deleting your account or workspace</strong> removes the associated
        content. Some records are soft-deleted first so an accidental deletion can
        be reversed, then purged.
    </li>
    <li>
        Server and webhook logs are kept for a limited period for security and
        debugging, then discarded.
    </li>
</ul>

<h2 id="your-rights">Your rights</h2>
<p>
    You can ask us to give you a copy of your data, correct it, delete it, or stop
    processing it. Depending on where you live you may also have the right to
    complain to a data protection authority. You can revoke our access to your
    Google account at any time at
    <a href="https://myaccount.google.com/permissions" rel="noopener" target="_blank">myaccount.google.com/permissions</a>,
    and to your Microsoft account at
    <a href="https://account.microsoft.com/privacy" rel="noopener" target="_blank">account.microsoft.com/privacy</a>.
</p>
<p>
    To exercise any of these, email
    <a href="mailto:{{ config('legal.privacy_email') }}">{{ config('legal.privacy_email') }}</a>.
    We respond within 30 days.
</p>

<h2 id="children">Children</h2>
<p>
    {{ config('legal.product') }} is not intended for anyone under 16, and we do not knowingly collect
    their data. If you believe a child has given us personal data, contact us and
    we will delete it.
</p>

<h2 id="international">International transfers</h2>
<p>
    Our processors operate globally, so your data may be processed in countries
    other than your own. Where required, we rely on appropriate safeguards such as
    standard contractual clauses.
</p>

<h2 id="changes">Changes to this policy</h2>
<p>
    If we change this policy materially we will update the effective date above and
    notify you in the application before the change takes effect.
</p>

<h2 id="contact">Contact</h2>
<p>
    {{ config('legal.entity') }}@if(config('legal.address'))<br />{{ config('legal.address') }}@endif<br />
    <a href="mailto:{{ config('legal.privacy_email') }}">{{ config('legal.privacy_email') }}</a>
</p>

@endsection
