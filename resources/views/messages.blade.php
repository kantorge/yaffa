@if( !empty( Session( 'any_notifications' ) ) )
    @foreach (Session('notification_collection') as $notification)
        <div class="alert alert-{{ $notification['type'] }} {{ ($notification['dismissable'] ? 'alert-dismissable' : '') }}">
            {{ (  $notification['dismissable']
                ? '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">X</button>'
                : '')
            }}
            {{ (  $notification['title'] || $notification['icon']
                ? "<h4>".(  $notification['icon']
                        ? "<i class='icon fa fa-" . $notification['icon'] . "'></i> "
                        : ""
                        ).$notification['title']."</h4>"
                : ''
                )
            }}
            {{ $notification['message'] }}
        </div>
        <!-- /.alert -->
    @endforeach
@endif