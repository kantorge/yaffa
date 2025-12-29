<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Upload Investments CSV</title>
    <style>body{font-family:Segoe UI,Roboto,Arial;padding:20px;background:#f7f7f9} .card{background:white;padding:16px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:900px;margin:20px auto}</style>
</head>
<body>
    <div class="card">
        <h2>Upload Investments CSV</h2>
        <form method="POST" action="{{ route('investment.upload_csv') }}" enctype="multipart/form-data">
            @csrf
            <div>
                <label for="csv_file">CSV File</label><br>
                <input type="file" id="csv_file" name="csv_file" required>
            </div>
            <div style="margin-top:12px">
                <button type="submit">Upload</button>
            </div>
        </form>
        @if(session('upload_result'))
            <div id="uploadResult" style="margin-top:12px">
                <pre>{{ print_r(session('upload_result'), true) }}</pre>
            </div>

            @php $res = session('upload_result'); @endphp
            @if(is_array($res) && isset($res['import_id']))
                <div id="importProgress" style="margin-top:12px">
                    <div>Import status: <span id="impStatus">queued</span></div>
                    <div>Processed rows: <span id="impProcessed">0</span></div>
                    <div>Errors: <pre id="impErrors">[]</pre></div>
                </div>

                <script>
                (function(){
                    var importId = {{ (int)$res['import_id'] }};
                    var statusEl = document.getElementById('impStatus');
                    var processedEl = document.getElementById('impProcessed');
                    var errorsEl = document.getElementById('impErrors');

                    function poll(){
                        fetch('/imports/' + importId + '/status', {credentials: 'same-origin'})
                            .then(function(resp){
                                if (!resp.ok) throw new Error('status ' + resp.status);
                                return resp.json();
                            })
                                        .then(function(json){
                                            statusEl.textContent = json.status || 'unknown';
                                            processedEl.textContent = json.processed_rows || 0;
                                            errorsEl.textContent = JSON.stringify(json.errors || [], null, 2);
                                            if (json.status === 'finished' || json.status === 'failed') {
                                                // show download link if errors exist
                                                if ((json.errors || []).length > 0) {
                                                    var a = document.createElement('a');
                                                    a.href = '/imports/' + importId + '/errors';
                                                    a.textContent = 'Download errors (JSON)';
                                                    a.style.display = 'block';
                                                    a.style.marginTop = '8px';
                                                    document.getElementById('importProgress').appendChild(a);
                                                }
                                                return;
                                            }
                                            setTimeout(poll, 1500);
                                        })
                            .catch(function(err){
                                console.error('Import status poll failed', err);
                                setTimeout(poll, 3000);
                            });
                    }
                    // start polling
                    poll();
                })();
                </script>
            @endif
        @endif
    </div>
</body>
</html>