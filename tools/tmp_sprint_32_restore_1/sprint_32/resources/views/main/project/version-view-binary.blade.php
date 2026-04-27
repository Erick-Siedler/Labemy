<div class="zip-viewer-content">
    @if($isImage)
        <div class="zip-media">
            <img src="{{ route('versions.raw', ['version' => $version->id, 'path' => $path]) }}" alt="{{ basename($path) }}">
        </div>
    @elseif($isPdf)
        <div class="zip-media zip-media-pdf">
            <iframe
                src="{{ route('versions.raw', ['version' => $version->id, 'path' => $path]) }}"
                title="{{ basename($path) }}"
                loading="lazy"
            ></iframe>
        </div>
    @endif
</div>
