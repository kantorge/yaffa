@if(isset($notifications) && count($notifications) > 0)
    @foreach ($notifications as $notification)
        <div class="alert alert-{{ $notification['type'] }} {{ ($notification['dismissable'] ? 'alert-dismissable' : '') }}">
            @if($notification['dismissable'])
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">X</button>
            @endif
            @if($notification['title'] || $notification['icon'])
                <h4>
                    @if($notification['icon'])
                        <span class='icon fa fa-{{ $notification['icon'] }}'></span>
                    @endif
                    {{ $notification['title'] }}
                </h4>
            @endif
            {{ $notification['message'] }}
        </div>
        <!-- /.alert -->
    @endforeach
@endif
