@extends('shipping::layouts.app')

@section('content')
    <div class="container">
        <h2>建立物流表單</h2>
        <label for="shippingType">選擇物流類型：</label>
        <select id="shippingType" name="shippingType">
            <option value="normal">建立物流單</option>
            <option value="return">建立退貨物流</option>
        </select>
        <form id="shippingForm" action="{{ route('shipping.fedex.create') }}" method="POST">
            @csrf
            <input type="hidden" name="carrier" value="fedex">
            <input type="hidden" name="account_name" value="Alpharex_Fedex_Sandbox">
            <input type="hidden" name="account_number" value="802255209">
            <!-- 基本資料 -->
            <div class="row">
                <div class="col mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" required>
                </div>
                <div class="col mb-3">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" name="invoice_date" class="form-control" required placeholder="YYYYMMDD">
                </div>
                <div class="col mb-3">
                    <label class="form-label">Customer PO Number</label>
                    <input type="text" name="customer_po_number" class="form-control">
                </div>
                <div class="col mb-3">
                    <label class="form-label">Market Order ID</label>
                    <input type="text" name="market_order_id" class="form-control">
                </div>
            </div>
            <!-- Shipper -->
            <div class="package border p-3 mb-3">
                <h4>Shipper</h4>
                <div class="row">
                    <div class="mb-3 col">
                        <input type="text" name="shipper[contact][personName]" class="form-control" placeholder="Name"
                            value="Shipper">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="shipper[contact][phoneNumber]" class="form-control" placeholder="Phone"
                            value="1234567890">
                    </div>
                    <div class="mb-3 col">
                        <input type="email" name="shipper[contact][emailAddress]" class="form-control" placeholder="Email"
                            value="Shipper@gmail.com">
                    </div>
                </div>
                <h6>Address</h6>
                <div class="row">
                    <div class="mb-3 col">
                        <select name="shipper[address][countryCode]" class="form-select">
                            @foreach(config('fedex.CountryCodes') as $label => $value)
                                <option value="{{ $value }}" {{ ($value == 'US') ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="shipper[address][streetLines][0]" class="form-control"
                            placeholder="streetLines" value="5300 Irwindale Ave">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="shipper[address][city]" class="form-control" placeholder="city"
                            value="Baldwin Park">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="shipper[address][stateOrProvinceCode]" class="form-control"
                            placeholder="stateOrProvinceCode" value="CA">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="shipper[address][postalCode]" class="form-control" placeholder="postalCode"
                            value="91706">
                    </div>
                </div>
            </div>
            <!-- Recipient -->
            <div id="Recipient" class="package border p-3 mb-3">
                <h4>Recipient</h4>
                <div class="row">
                    <div class="mb-3 col">
                        <input type="text" name="recipient[contact][personName]" class="form-control" placeholder="Name"
                            value="Recipient">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="recipient[contact][phoneNumber]" class="form-control" placeholder="Phone"
                            value="17195343655">
                    </div>
                    <div class="mb-3 col">
                        <input type="email" name="recipient[contact][emailAddress]" class="form-control" placeholder="Email"
                            value="Recipient@gmail.com">
                    </div>
                </div>
                <h6>Address</h6>
                <div class="row">
                    <div class="mb-3 col">
                        <select name="recipient[address][countryCode]" class="form-select">
                            @foreach(config('fedex.CountryCodes') as $label => $value)
                                <option value="{{ $value }}" {{ ($value == 'US') ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="recipient[address][streetLines][0]" class="form-control"
                            placeholder="streetLines" value="1809 Frederick St">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="recipient[address][city]" class="form-control" placeholder="city"
                            value="Fort Worth">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="recipient[address][stateOrProvinceCode]" class="form-control"
                            placeholder="stateOrProvinceCode" value="TX">
                    </div>
                    <div class="mb-3 col">
                        <input type="text" name="recipient[address][postalCode]" class="form-control"
                            placeholder="postalCode" value="76107">
                    </div>
                </div>
            </div>
            <!-- Return -->
            <div id="Return">
                <div class="package border p-3 mb-3">
                    <h4>Return</h4>
                    <div class="row">
                        <div class="mb-3 col">
                            <input type="text" name="return_address[contact][personName]" class="form-control"
                                placeholder="Name" value="return_address">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_address[contact][phoneNumber]" class="form-control"
                                placeholder="Phone" value="17195343655">
                        </div>
                        <div class="mb-3 col">
                            <input type="email" name="return_address[contact][emailAddress]" class="form-control"
                                placeholder="Email" value="return_address@gmail.com">
                        </div>
                    </div>
                    <h6>Address</h6>
                    <div class="row">
                        <div class="mb-3 col">
                            <select name="return_address[address][countryCode]" class="form-select">
                                @foreach(config('fedex.CountryCodes') as $label => $value)
                                    <option value="{{ $value }}" {{ ($value == 'US') ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_address[address][streetLines][0]" class="form-control"
                                placeholder="streetLines" value="1809 Frederick St">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_address[address][city]" class="form-control" placeholder="city"
                                value="Fort Worth">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_address[address][stateOrProvinceCode]" class="form-control"
                                placeholder="stateOrProvinceCode" value="TX">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_address[address][postalCode]" class="form-control"
                                placeholder="postalCode" value="76107">
                        </div>
                    </div>
                </div>

                <div class="package border p-3 mb-3">
                    <h4>Return Info</h4>
                    <div class="row">
                        <div class="mb-3 col">
                            <input type="text" name="original_tracking_number" class="form-control"
                                placeholder="original_tracking_number">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_reason" class="form-control" value="return_reason">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_instructions" class="form-control" value="return_instructions">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="rma_number" class="form-control" value="return_instructions">
                        </div>
                        <div class="mb-3 col">
                            <input type="text" name="return_authorization_number" class="form-control"
                                value="return_authorization_number">
                        </div>

                    </div>
                    <div class="row">
                        <div class="mb-3 col">
                            <label class="form-label">The specifies the return Type</label>
                            <select name="return_type" class="form-select">
                                <option value="PENDING">PENDING</option>
                                <option value="PRINT_RETURN_LABEL">PRINT_RETURN_LABEL</option>
                            </select>

                            <span>這會指定傳回類型。對於列印的退貨標籤貨件，需要設定為 PRINT_RETURN_LABEL。對於電子郵件退貨標籤貨件，returnType 必須設定為 PENDING，而
                                pendingShipmentDetail 必須設定為 EMAIL。</span>
                        </div>
                        <div class="mb-3 col">
                            ship_datestamp
                            <input type="date" name="ship_datestamp" class="form-control" value="ship_datestamp">
                        </div>
                        <div class="mb-3 col">
                            expiration_time
                            <input type="date" name="expiration_time" class="form-control" value="expiration_time">
                        </div>
                    </div>
                </div>
            </div>
            <div class="border p-3 mb-3">
                <h5>Create Shipment</h5>
                <div class="row">
                    <div class="col mb-3">
                        <label class="form-label">labelResponseOptions</label>
                        <select name="labelResponseOptions" class="form-select">
                            <option value="URL_ONLY">URL_ONLY</option>
                            <option value="LABEL">LABEL</option>
                        </select>
                    </div>

                    <!-- Service Type -->
                    <div class="col mb-3">
                        <label class="form-label">Service Type</label>
                        <select name="service_type" class="form-select">
                            @foreach(config('fedex.ServiceTypes') as $label => $value)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col mb-3">
                        <label class="form-label">Package Types</label>
                        <select name="packaging_type" class="form-select">
                            @foreach(config('fedex.PackageTypes') as $label => $value)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col mb-3">
                        <label class="form-label">Pickup Types</label>
                        <select name="pickup_type" class="form-select">
                            @foreach(config('fedex.PickupTypes') as $label => $value)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <!-- 簽名 -->
            <div class="border p-3 mb-3">
                <h5>Signature</h5>
                <div class="row">
                    <div class="col mb-3">
                        <label class="form-label">Signature Required?</label>
                        <select name="signature_required" class="form-select">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <div class="col mb-3">
                        <label class="form-label">Signature Type</label>
                        <select name="signature_type" class="form-select">
                            <option value="ADULT">ADULT</option>
                            <option value="SERVICE_DEFAULT">SERVICE_DEFAULT</option>
                            <option value="NO_SIGNATURE_REQUIRED">NO_SIGNATURE_REQUIRED</option>
                            <option value="INDIRECT">INDIRECT</option>
                            <option value="DIRECT">DIRECT</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Packages -->
            <div>
                <div class="package border p-3 mb-3">
                    <div class="mt-3">
                        <h5>Package</h5>
                        <div id="packages-wrapper">
                            <div class="row  mb-2">
                                <div class="col">
                                    weight
                                    <input type="number" name="packages[0][weight]" class="form-control"
                                        placeholder="Weight" value="34">
                                </div>
                                <div class="col">
                                    weight_units
                                    <select name="packages[0][weight_units]" class="form-select">
                                        <option value="LB">LB</option>
                                        <option value="KG">KG</option>
                                    </select>
                                </div>
                                <div class="col">
                                    length
                                    <input type="number" name="packages[0][length]" class="form-control"
                                        placeholder="Length" value="49">
                                </div>
                                <div class="col">
                                    dimensions_units
                                    <select name="packages[0][dimensions_units]" class="form-select">
                                        <option value="IN">IN</option>
                                        <option value="CM">CM</option>
                                    </select>
                                </div>
                                <div class="col">
                                    width
                                    <input type="number" name="packages[0][width]" class="form-control" placeholder="Width"
                                        value="10">
                                </div>
                                <div class="col">
                                    height
                                    <input type="number" name="packages[0][height]" class="form-control"
                                        placeholder="Height" value="6">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button id="add-package" type="button" class="btn btn-sm btn-secondary">新增 Item</button>
                </div>
            </div>

            <!-- Items -->
            <div class="item border p-3 mb-3">
                <div class="mt-3">
                    <h6>Items</h6>
                    <div id="items-wrapper">
                        <div class="row mb-2">
                            <div class="col">
                                <select name="items[0][UnitOfMeasurement]" class="form-select">
                                    @foreach(config('fedex.UnitOfMeasurement') as $label => $value)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col">
                                <input type="number" min="0" name="items[0][quantity]" class="form-control"
                                    placeholder="Qty">
                            </div>
                            <div class="col">
                                <input type="text" name="items[0][unitPrice]" class="form-control" placeholder="Unit Price">
                            </div>
                            <div class="col">
                                <input type="text" name="items[0][description]" class="form-control"
                                    placeholder="Description">
                            </div>
                        </div>
                    </div>
                    <button id="add-item" type="button" class="btn btn-sm btn-secondary">新增 Item</button>
                </div>
            </div>
            <div class="row mb-5">
                <button type="submit" class="btn btn-success">送出</button>
            </div>
        </form>
    </div>

    <!-- JS for dynamic add/remove -->
    <script>
        let packageIndex = 1;

        document.getElementById('add-package').addEventListener('click', function () {
            let wrapper = document.getElementById('packages-wrapper');
            let newPackage = document.createElement('div');
            // newPackage.classList.add('package', 'border', 'p-3', 'mb-3');
            newPackage.innerHTML = `
                <div class="row mb-2">
                    <div class="col"><input type="number" min="0" name="packages[${packageIndex}][weight]" class="form-control" placeholder="Weight"></div>
                    <div class="col">
                        <select name="packages[${packageIndex}][units]" class="form-select">
                            <option value="LB">LB</option>
                            <option value="KG">KG</option>
                        </select>
                    </div>
                    <div class="col"><input type="number" min="0" name="packages[${packageIndex}][length]" class="form-control" placeholder="Length"></div>
                    <div class="col">
                        <select name="packages[${packageIndex}][dimensions_units]" class="form-select">
                            <option value="IN">IN</option>
                            <option value="CM">CM</option>
                        </select>
                    </div>
                    <div class="col"><input type="number" min="0" name="packages[${packageIndex}][width]" class="form-control" placeholder="Width"></div>
                    <div class="col"><input type="number" min="0" name="packages[${packageIndex}][height]" class="form-control" placeholder="Height"></div>
                </div>
                `;
            wrapper.appendChild(newPackage);
            packageIndex++;
        });

        let itemIndex = 1;
        window.fedexUOM = @json(config('fedex.UnitOfMeasurement'));
        // 動態新增 item
        document.getElementById('add-item').addEventListener('click', function () {
            let itemsWrapper = document.getElementById('items-wrapper');

            // 動態生成 select
            let select = document.createElement('select');
            select.name = `items[${itemIndex}][UnitOfMeasurement]`;
            select.classList.add('form-select');

            for (let label in window.fedexUOM) {
                let value = window.fedexUOM[label];
                let option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            }

            let newItem = document.createElement('div');
            newItem.classList.add('row', 'mb-2');
            newItem.innerHTML = `
                <div class="col"></div>
                <div class="col"><input type="number" min="0" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Qty"></div>
                <div class="col"><input type="text" name="items[${itemIndex}][unitPrice]" class="form-control" placeholder="Unit Price"></div>
                <div class="col"><input type="text" name="items[${itemIndex}][description]" class="form-control" placeholder="Description"></div>
            `;
            newItem.querySelector('.col').appendChild(select);

            itemsWrapper.appendChild(newItem);
            itemIndex++;
        });


        document.getElementById('shippingType').addEventListener('change', function () {
            const form = document.getElementById('shippingForm');
            document.getElementById("Recipient").style.display = "none";
            document.getElementById("Return").style.display = "none";
            if (this.value === 'return') {
                document.getElementById("Return").style.display = "block";
                form.action = "{{ route('shipping.fedex.createReturn') }}";
            } else {
                document.getElementById("Recipient").style.display = "block";
                form.action = "{{ route('shipping.fedex.create') }}";
            }
        });
        document.getElementById("Return").style.display = "none";
    </script>
@endsection