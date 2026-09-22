@extends('legal.layout')

@section('title', 'Terms of Service')
@section('description', 'The terms on which you may use ' . config('legal.product') . ', including subscriptions, acceptable use and liability.')

@section('body')

<p>
    These terms are an agreement between you and {{ config('legal.entity') }} covering your use of
    {{ config('legal.product') }}. By creating an account or using the service you accept them. If you
    are accepting on behalf of an organisation, you confirm you are authorised to
    bind it.
</p>

<h2 id="service">1. The service</h2>
<p>
    {{ config('legal.product') }} is a work management application providing projects, tasks, notes,
    goals, meetings and optional synchronisation with third-party calendar, email
    and messaging accounts. We may add, change or remove features over time. If we
    remove something you materially rely on, we will tell you in advance.
</p>

<h2 id="accounts">2. Your account</h2>
<ul>
    <li>You must give accurate registration details and keep them current.</li>
    <li>You are responsible for activity under your account and for keeping your credentials secure. Tell us promptly if you suspect unauthorised access.</li>
    <li>You must be at least 16 years old.</li>
    <li>A workspace owner controls that workspace's members and content, and can remove members and their access.</li>
</ul>

<h2 id="your-content">3. Your content</h2>
<p>
    You keep all ownership of the content you put into {{ config('legal.product') }}. You grant us only
    the limited licence needed to host, process, transmit, back up and display that
    content in order to run the service for you. We do not use your content for any
    other purpose.
</p>
<p>
    You are responsible for having the right to upload what you upload, and for
    ensuring that sharing it through {{ config('legal.product') }} does not breach anyone's rights or
    any law that applies to you.
</p>

<h2 id="connected">4. Connected accounts</h2>
<p>
    When you connect a Google, Microsoft, Twilio or WhatsApp account you authorise
    us to access it on your behalf for the purposes described in our
    <a href="{{ route('legal.privacy') }}">Privacy Policy</a>. Your use of those services remains
    governed by their own terms, and we are not responsible for them. You can
    revoke our access at any time; doing so will stop the related features working.
</p>

<h2 id="acceptable-use">5. Acceptable use</h2>
<p>You may not:</p>
<ul>
    <li>break the law, infringe intellectual property, or violate anyone's privacy;</li>
    <li>send spam, bulk unsolicited messaging, or anything deceptive, through our messaging or email features;</li>
    <li>upload malware, or attempt to breach, probe or disrupt the service or its infrastructure;</li>
    <li>access another customer's data, or attempt to;</li>
    <li>reverse engineer the service, or resell it, except as the law expressly permits;</li>
    <li>impose an unreasonable load on the service, including through automated scraping.</li>
</ul>
<p>We may suspend an account that breaches this section, and will tell you why where we lawfully can.</p>

<h2 id="fees">6. Subscriptions and payment</h2>
<ul>
    <li>Paid plans are billed in advance at the price and interval shown at purchase. Prices are exclusive of any applicable taxes unless stated.</li>
    <li>A monthly subscription renews automatically until you cancel. You can cancel at any time; cancellation takes effect at the end of the paid period, and you keep access until then.</li>
    <li>A lifetime plan is a single payment granting access for as long as we operate the service. It is not a guarantee of any particular period of operation.</li>
    <li>Except where the law requires otherwise, fees already paid are non-refundable. If we materially reduce the service during a period you have paid for, contact us and we will make it right.</li>
    <li>If we change prices, existing subscribers get at least 30 days' notice before the change applies to their renewal.</li>
</ul>

<h2 id="availability">7. Availability</h2>
<p>
    We work to keep {{ config('legal.product') }} available and our data safe, but we do not promise
    uninterrupted service. Maintenance, provider outages and events outside our
    control can cause downtime. Features that depend on a third party — calendar
    sync, email, SMS — depend on that third party remaining available to us.
</p>

<h2 id="termination">8. Suspension and termination</h2>
<p>
    You can stop using {{ config('legal.product') }} and delete your account at any time. We may
    suspend or terminate an account that materially breaches these terms, or where
    we are required to by law. If we terminate without cause, we will refund the
    unused portion of any prepaid fee.
</p>
<p>
    After termination we delete your content as described in the
    <a href="{{ route('legal.privacy') }}#retention">Privacy Policy</a>. Export anything you want to
    keep before you delete your account.
</p>

<h2 id="warranty">9. Disclaimer</h2>
<p>
    To the fullest extent the law allows, the service is provided "as is" and "as
    available", without warranties of any kind, whether express or implied,
    including any implied warranty of merchantability, fitness for a particular
    purpose or non-infringement. Nothing in these terms excludes a right you have
    under consumer law that cannot lawfully be excluded.
</p>

<h2 id="liability">10. Limitation of liability</h2>
<p>
    To the fullest extent the law allows, we are not liable for indirect,
    incidental, special, consequential or punitive damages, or for lost profits,
    revenue, data or goodwill. Our total aggregate liability arising out of or
    relating to the service is limited to the greater of the amount you paid us in
    the twelve months before the event giving rise to the claim, or one hundred
    United States dollars.
</p>
<p>
    Nothing here limits liability for fraud, for death or personal injury caused by
    negligence, or for anything else that cannot lawfully be limited.
</p>

<h2 id="indemnity">11. Indemnity</h2>
<p>
    You will indemnify us against claims, losses and reasonable legal costs arising
    from your content, or from your use of the service in breach of these terms or
    of the law.
</p>

<h2 id="changes">12. Changes to these terms</h2>
<p>
    We may update these terms. For material changes we will give notice in the
    application at least 30 days before they take effect. Continuing to use the
    service after that means you accept the new terms; if you do not, stop using
    the service and, for a prepaid plan, contact us about the unused portion.
</p>

<h2 id="law">13. Governing law</h2>
<p>
    These terms are governed by the laws of {{ config('legal.jurisdiction') }}, and the courts of
    {{ config('legal.jurisdiction') }} have exclusive jurisdiction, without affecting any right you
    have to bring a claim in your country of residence where the law gives you that
    right.
</p>

<h2 id="general">14. General</h2>
<p>
    If a provision is held unenforceable, the rest stays in force. Our not
    enforcing a term is not a waiver of it. You may not assign these terms without
    our consent; we may assign them to a successor in a merger or acquisition.
    These terms and the Privacy Policy are the entire agreement between us on this
    subject.
</p>

<h2 id="contact">15. Contact</h2>
<p>
    {{ config('legal.entity') }}@if(config('legal.address'))<br />{{ config('legal.address') }}@endif<br />
    <a href="mailto:{{ config('legal.support_email') }}">{{ config('legal.support_email') }}</a>
</p>

@endsection
