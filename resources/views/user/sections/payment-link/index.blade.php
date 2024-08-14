@extends('user.layouts.master')

@section('content')
<div class="body-wrapper">
    <div class="table-area">
        <div class="dashboard-header-wrapper">
            <h4 class="title">{{ $page_title }}</h4>
            <div class="dashboard-btn-wrapper">
                <div class="dashboard-btn">
                    <a href="{{ setRoute('user.payment-link.create') }}" class="btn--base active"><i class="las la-plus me-1"></i> {{ __('Create Link') }}</a>
                </div>
            </div>
        </div>
        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payment_links as $item)
                            <tr>
                                <td>{{ $item->title }}</td>
                                <td>{{ $item->linkType }}</td>
                                <td>{{ $item->amountCalculation }}</td>
                                <td><span class="{{ $item->stringStatus->class }}">{{ $item->stringStatus->value }}</span></td>
                                <td>{{ dateFormat('d M Y, h:i:s A', $item->created_at) }}</td>
                                <td>
                                    <div class="d-flex">
                                        <div>
                                            <button type="button" onclick="copyToClipBoard('copy-share-link-{{ $item->id }}')" class="btn--base btn"><i class="las la-clipboard"></i></button>
                                            <input type="hidden" id="copy-share-link-{{ $item->id }}" value="{{ setRoute('payment-link.share', $item->token) }}">
                                        </div>
                                        <div class="action-btn ms-1">
                                            <button type="button" class="btn--base btn"><i class="las la-ellipsis-v"></i></button>
                                            <ul class="action-list">
                                                @if ($item->status == 1)
                                                    <li><a href="{{ setRoute('user.payment-link.edit', $item->id) }}">{{ __('Edit') }}</a></li>
                                                @endif
                                                @if ($item->status == 1)
                                                    <li><a href="" class="status_change" data-target="{{ $item->id }}" data-type="Close">{{ __('Close') }}</a></li>
                                                @else
                                                    <li><a href="" class="status_change" data-target="{{ $item->id }}" data-type="Active">{{ __('Active') }}</a></li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <nav>
           {{ $payment_links->links() }}
        </nav>
    </div>
</div>
@endsection

@push('script')
<script>
    function copyToClipBoard(element) {
        var copyText = document.getElementById(element);
        copyText.select();
        navigator.clipboard.writeText(copyText.value);
        notification('success', 'URL Copied To Clipboard!');
    }

    $(".status_change").click(function(e){
        e.preventDefault();
        var target  = $(this).data('target');
        var actionRoute = "{{ route('user.payment-link.status') }}";
        var message = `Are you sure to change <strong>Status</strong>?`;
        var type = $(this).data('type');
        openAlertModal(actionRoute,target,message,type,"POST");
    });
</script>
@endpush
