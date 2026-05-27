<div>
    <h2>Содержание</h2>
    <ul class="toc">
        @foreach ($entries as $entry)
            <li><a href="#{{ $entry['anchor'] }}">{{ $entry['title'] }}</a></li>
        @endforeach
    </ul>
</div>
