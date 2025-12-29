@extends('template.layouts.page')

@section('title_postfix', __('Transaction Import Rules'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Transaction Import Rules'))

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Import Rules') }}</h5>
            <div>
                <a href="{{ route('transaction-import-rules.test') }}" class="btn btn-info btn-sm me-2">
                    <i class="fa fa-flask"></i> {{ __('Test Rules') }}
                </a>
                <a href="{{ route('transaction-import-rules.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> {{ __('Add Rule') }}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        @if($rules->isEmpty())
            <p class="text-muted">{{ __('No import rules defined yet. Create a rule to automatically transform transactions during import.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th>{{ __('Description Pattern') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('Target') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                            <tr class="{{ $rule->active ? '' : 'table-secondary' }}">
                                <td>{{ $rule->priority }}</td>
                                <td>
                                    @if($rule->account)
                                        <span class="badge bg-info">{{ $rule->account->name }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('All Accounts') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $rule->description_pattern }}</code>
                                    @if($rule->use_regex)
                                        <span class="badge bg-warning text-dark">Regex</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rule->action === 'convert_to_transfer')
                                        <span class="badge bg-primary">{{ __('Transfer') }}</span>
                                    @elseif($rule->action === 'merge_payee')
                                        <span class="badge bg-success">{{ __('Merge Payee') }}</span>
                                    @elseif($rule->action === 'skip')
                                        <span class="badge bg-danger">{{ __('Skip') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($rule->action) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rule->action === 'convert_to_transfer' && $rule->transferAccount)
                                        {{ $rule->transferAccount->name }}
                                    @elseif($rule->action === 'merge_payee' && $rule->mergePayee)
                                        {{ $rule->mergePayee->name }}
                                        @if($rule->append_original_to_comment)
                                            <span class="badge bg-info text-white" title="{{ __('Original payee name will be appended to comment') }}">
                                                <i class="fa fa-comment"></i>
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rule->active)
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('transaction-import-rules.edit', $rule) }}" 
                                           class="btn btn-outline-primary" 
                                           title="{{ __('Edit') }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <form action="{{ route('transaction-import-rules.destroy', $rule) }}" 
                                              method="POST" 
                                              class="d-inline"
                                              onsubmit="return confirm('{{ __('Are you sure you want to delete this rule?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-outline-danger" 
                                                    title="{{ __('Delete') }}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">{{ __('How Import Rules Work') }}</h6>
    </div>
    <div class="card-body">
        <ul>
            <li><strong>{{ __('Priority') }}:</strong> {{ __('Lower numbers are checked first. If a rule matches, no further rules are checked.') }}</li>
            <li><strong>{{ __('Account') }}:</strong> {{ __('If set, rule only applies when importing to that specific account. Leave blank for global rules.') }}</li>
            <li><strong>{{ __('Description Pattern') }}:</strong> {{ __('Text to match in transaction descriptions (case-insensitive). Use "Regex" for advanced pattern matching.') }}</li>
            <li><strong>{{ __('Convert to Transfer') }}:</strong> {{ __('Transforms a payee transaction into a transfer between two accounts.') }}</li>
            <li><strong>{{ __('Merge Payee') }}:</strong> {{ __('Replaces the matched payee with a specific payee, optionally appending the original name to the comment.') }}</li>
            <li><strong>{{ __('Skip') }}:</strong> {{ __('Don\'t import transactions matching this pattern (useful to avoid duplicates).') }}</li>
        </ul>
    </div>
</div>

@endsection
