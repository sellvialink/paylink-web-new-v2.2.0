@extends('user.layouts.master')

@section('content')
<div class="body-wrapper">
                <div class="row justify-content-center mb-20-none">
                    <div class="col-xl-6 col-lg-6 mb-20">
                        <div class="custom-card mt-10">
                            <div class="dashboard-header-wrapper">
                                <h4 class="title"></h4></h4>
                            </div>
                            <div class="card-body">
                                <div class="payment-loader-wrapper">
                                    <div class="payment-loader">
                                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                                            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                                        </svg>
                                    </div>
                                    <h4 class="title">{{ __('Invoice Created Successfully') }}.</h4>
                                </div>
                                <div class="row">
                                    <div class="col-xl-12 col-lg-12 form-group">
                                        <label>{{ __('Copy link') }}</label>
                                        <div class="input-group">
                                            <input type="text" class="form--control copy-from-input" id="copy-share-link" value="{{ setRoute('invoice.share', $invoice->token) }}" readonly>
                                            <button class="input-group-text copy-text-btn"><i class="las la-copy"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12">
                                    <a  href="{{ setRoute('user.invoice.create') }}" class="btn--base w-100">{{ __('Create Another Invoice') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
@endsection

@push('script')
    <script>
        $(document).ready(function () {
            $(document).on('click', '.copy-text-btn', function(){
                copyToClipBoard();
            })
        });
        function copyToClipBoard() {
            var copyText = document.getElementById("copy-share-link");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);

            notification('success', 'URL Copied To Clipboard!');
        }
    </script>
@endpush
