@extends('template.layouts.page')

@section('title', __('MoneyHub Transaction Upload'))

@section('content_header', __('MoneyHub Transaction Upload'))



@section('content')
<div class="container py-3">
    <div id="moneyhub-upload-app">
        <money-hub-upload-tool></money-hub-upload-tool>
    </div>

    <hr />

    <h3>Quick upload (manual)</h3>
    <form method="POST" action="{{ route('import.moneyhub.upload') }}" enctype="multipart/form-data">
        @csrf
        <div>
            <label for="file">CSV File</label><br>
            <input type="file" id="file" name="file" required>
        </div>
        <div style="margin-top:8px">
            <label for="account_id">Optional account (map transactions to this account)</label><br>
            <select name="account_id" id="account_id">
                <option value="">-- use automatic match --</option>
                @foreach(auth()->user()->accounts()->orderBy('name')->get() as $acct)
                    <option value="{{ $acct->id }}">{{ $acct->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="btn btn-primary">Upload & queue</button>
        </div>
    </form>

    @if(session('upload_result'))
        <pre style="margin-top:12px">{{ print_r(session('upload_result'), true) }}</pre>
        @php $res = session('upload_result'); @endphp
        @if(is_array($res) && isset($res['import_id']))
            <div id="importProgress">
                <div>Status: <span id="impStatus">queued</span></div>
                <div>Processed: <span id="impProcessed">0</span></div>
                <pre id="impErrors">[]</pre>
            </div>
            <script>
            (function(){
                var importId = {{ (int)$res['import_id'] }};
                function poll(){
                    fetch('/imports/' + importId + '/status', {credentials: 'same-origin'})
                        .then(r => r.json())
                        .then(function(json){
                            document.getElementById('impStatus').textContent = json.status || 'unknown';
                            document.getElementById('impProcessed').textContent = json.processed_rows || 0;
                            document.getElementById('impErrors').textContent = JSON.stringify(json.errors || [], null, 2);
                            if (json.status === 'finished' || json.status === 'failed') {
                                if ((json.errors || []).length) {
                                    var a = document.createElement('a');
                                    a.href = '/imports/' + importId + '/errors';
                                    a.textContent = 'Download errors (JSON)';
                                    a.style.display = 'block';
                                    document.getElementById('importProgress').appendChild(a);
                                }
                                return;
                            }
                            setTimeout(poll, 1500);
                        })
                        .catch(function(){ setTimeout(poll, 3000); });
                }
                poll();
            })();
            </script>
        @endif
    @endif
</div>
@endsection
