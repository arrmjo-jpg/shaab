{!! '<?xml version="1.0" encoding="UTF-8"?>' . "\n" !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>{{ $channel['title'] }}</title>
    <link>{{ $channel['link'] }}</link>
    <description>{{ $channel['description'] }}</description>
    <language>{{ $channel['language'] }}</language>
    <lastBuildDate>{{ $channel['lastBuildDate'] }}</lastBuildDate>
    <atom:link href="{{ $channel['feedUrl'] }}" rel="self" type="application/rss+xml"/>
@foreach ($items as $item)
    <item>
      <title>{{ $item['title'] }}</title>
      <link>{{ $item['link'] }}</link>
      <guid isPermaLink="true">{{ $item['guid'] }}</guid>
      <pubDate>{{ $item['pubDate'] }}</pubDate>
      <description>{{ $item['description'] }}</description>
    </item>
@endforeach
  </channel>
</rss>
