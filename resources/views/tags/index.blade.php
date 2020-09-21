@extends('adminlte::page')

@section('title', 'Tags')

@section('content_header')
    <h1>Tags</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            <div class="card-tools">
                <a href="/tags/create" class="btn btn-success" title="New account group"><i class="fa fa-plus"></i></a>
            </div>
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <table class="table table-bordered table-hover dataTable" role="grid" id="table"></table>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

@stop

@section('plugins.Datatables', true)

@section('js')
    <script>
        var tableData = <?=json_encode($tags)?>;

        $(document).ready( function () {
            $('#table').DataTable({
                data: tableData,
                columns: [
                {
                    data: "id",
                    title: "{{\App\Tag::label('id')}}"
                },
                {
                    data: "name",
                    title: "{{\App\Tag::label('name')}}"
                },
                {
                    data: "id",
                    title: "Actions",
                    render: function ( data, type, row, meta ) {
                        return '' +
                            '<a href="' + row.edit_url +'" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                            '<button class="btn btn-sm btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + row.id + '" action="' + row.delete_url + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="{{ csrf_token() }}"></form>';
                    },
                    orderable: false
                }
                ],
                order: [[ 1, 'asc' ]]
            });

            $('.data-delete').on('click', function (e) {
                if (!confirm('Are you sure to want to delete this item?')) return;
                e.preventDefault();
                $('#form-delete-' + $(this).data('form')).submit();
            });
        });
    </script>
@stop