<div class="component-row">

    <div class="row">

    <div class="
        @if (isset($width) && $width == 'small')
            col-xs-2 col-sm-1 col-sm-offset-1 col-lg-offset-2
        @else 
            col-xs-2 col-sm-1 col-lg-offset-1
        @endif
        text-right
    "
        style="height: {{ isset($height) && $height == 'small' ? '2.7em' : '4.3em' }}"
    
    >

        @if (isset($image_link)) <a href="{{ $image_link }}"> @endif

        @if (isset($image))
            
            @include('component.image', [
                'image' => $image,
                'options' => '-circle',
                'height' => isset($height) ? $height: null
            ])

        @endif
         
        @if (isset($image_link)) </a> @endif

    </div>

    <div class="
        content
        @if (isset($width) && $width == 'small')
            col-xs-7 col-sm-8 col-lg-6
        @else 
            col-xs-7 col-sm-10 col-lg-8
        @endif
    "

        style="height: {{ isset($height) && $height == 'small' ? '2.7em' : '4.3em' }}"

    >
        <div>

            <div class="title">

                @if (isset($heading_link)) <a href="{{ $heading_link }}"> @endif
            
                @if (isset($heading)) <h3>{{ $heading }}</h3> @endif

                @if (isset($heading_link)) </a> @endif

            </div>

            <div class="text">

                @if (isset($text)) {!! $text !!} @endif

                {!! $actions or '' !!}
            
            </div>

        </div>

    </div>

    <div class="
        content
        @if (isset($width) && $width == 'small')
            col-xs-3 col-sm-1 col-lg-1
        @else 
            col-xs-3 col-sm-1 col-lg-1
        @endif
    "
    
        style="height: {{ isset($height) && $height == 'small' ? '2.9em' : '4.3em' }}"

    >
 
        {!! $extra or '' !!}

    </div>

    </div>

    @if (isset($body))

        <div class="row">

            <div class="
                @if (isset($width) && $width == 'small')
                    col-sm-8 col-sm-offset-2 col-lg-6 col-lg-offset-3
                @else 
                    col-sm-10 col-sm-offset-1 col-lg-8 col-lg-offset-2
                @endif
            ">

                {!! $body !!}

        </div>

    </div>

    @endif

</div>
