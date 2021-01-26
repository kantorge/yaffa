<div class="list-group-item transaction_item_row" id="{{ ($counter === '#' ? 'transaction_item_prototype' : '') }}">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Category</label>
                <select
                    class="form-control category"
                    name="transactionItems[{{ $counter }}][category_id]"
                    style="width:100%"
                >
                    @if(isset($item['category']))
                        <option value="{{ $item['category']['id'] }}" selected="selected">{{ $item['category']['full_name'] }}</option>
                    @endif
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label class="control-label">
                    Amount <span class='transaction_currency_from'></span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control transaction_item_amount"
                        name="transactionItems[{{ $counter }}][amount]"
                        type="text"
                        value="{{old('transactionItems.{$item}.amount', $item['amount'] ?? '')}}"
                    >
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-info load_remainder" title="Assign remaining amount to this item"><i class="fa fa-copy"></i></button>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-info toggle_transaction_detail" title="Show item details"><i class="fa fa-edit"></i></button>
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-danger remove_transaction_item" title="Remove transaction item"><i class="fa fa-minus"></i></button>
            </div>
        </div>
    </div>
    <div class="row transaction_detail_container" style="display:none;">
        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">Comment</label>
                <input class="form-control transaction_item_comment" name="transactionItems[{{ $counter }}][comment]" value="{{ $item['comment'] ?? '' }}" type="text">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">Tags</label>
                <select style="width: 100%" class="form-control tag" multiple="multiple" name="transactionItems[{{ $counter }}][tags][]">
                    @if(isset($item['tags']))
                        @foreach($item['tags'] as $tag)
                            <option value="{{ $tag['id'] }}" selected="selected">{{ $tag['name'] }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>
    </div>
</div>
