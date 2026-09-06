@props(['url'])
@inject('staticMedia', 'App\Services\StaticMediaService')
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $staticMedia->url('prodeals-email-logo.png', versioned: true) }}" class="logo" width="226" height="45" alt="ProDeals.lk">
</a>
</td>
</tr>
