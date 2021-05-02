@if(count($notifications))
    @foreach ($notifications as $notification)
        <div class="alert alert-{{ $notification['type'] }} {{ ($notification['dismissable'] ? 'alert-dismissable' : '') }}">
            @if($notification['dismissable'])
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">X</button>
            @endif
            @if($notification['title'] || $notification['icon'])
                <h4>
                    @if($notification['icon'])
                        <i class='icon fa fa-{{ $notification['icon'] }}'></i>
                    @endif
                    {{ $notification['title'] }}
                </h4>
            @endif
            {{ $notification['message'] }}
        </div>
        <!-- /.alert -->
    @endforeach
@endif
