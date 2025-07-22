<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
    @foreach($photos as $photo)
        <div class="bg-gray-50 rounded-lg overflow-hidden">
            <img 
                src="{{ asset('storage/'.$photo->path) }}" 
                class="w-full h-64 object-contain bg-white"
                loading="lazy"
            >
            <div class="p-2 text-xs text-gray-500">
                Фото {{ $loop->iteration }}/{{ $loop->count }}
            </div>
        </div>
    @endforeach
</div>