@php($amenities = config('realestate.amenities', []))
@php($areaUnits = config('realestate.area_units', []))
@php($selectedAmenities = old('amenities', $listing->amenities ?? []))

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ translate('Listing_Type') }}</label>
        <select name="listing_type" id="js-listing-type" class="form-select" required>
            <option value="house" {{ old('listing_type', $listing->listing_type) === 'house' ? 'selected' : '' }}>{{ translate('House') }}</option>
            <option value="land" {{ old('listing_type', $listing->listing_type) === 'land' ? 'selected' : '' }}>{{ translate('Land') }}</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ translate('Purpose') }}</label>
        <select name="purpose" id="js-purpose" class="form-select" required>
            <option value="sale" {{ old('purpose', $listing->purpose) === 'sale' ? 'selected' : '' }}>{{ translate('For_Sale') }}</option>
            <option value="rent" {{ old('purpose', $listing->purpose) === 'rent' ? 'selected' : '' }}>{{ translate('For_Rent') }}</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">{{ translate('Title') }}</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $listing->title) }}" maxlength="191" required>
    </div>
    <div class="col-12">
        <label class="form-label">{{ translate('Description') }}</label>
        <textarea name="description" class="form-control" rows="4" maxlength="5000">{{ old('description', $listing->description) }}</textarea>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ translate('Price') }}</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $listing->price) }}" required>
    </div>
    <div class="col-md-4" id="js-price-period-wrap">
        <label class="form-label">{{ translate('Price_Period') }}</label>
        <select name="price_period" class="form-select">
            <option value="">—</option>
            <option value="one_time" {{ old('price_period', $listing->price_period) === 'one_time' ? 'selected' : '' }}>{{ translate('One_time') }}</option>
            <option value="monthly" {{ old('price_period', $listing->price_period) === 'monthly' ? 'selected' : '' }}>{{ translate('Per_Month') }}</option>
            <option value="yearly" {{ old('price_period', $listing->price_period) === 'yearly' ? 'selected' : '' }}>{{ translate('Per_Year') }}</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ translate('Area_Size') }}</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="area_size" class="form-control" value="{{ old('area_size', $listing->area_size) }}">
            <select name="area_unit" class="form-select" style="max-width: 110px;">
                <option value="">—</option>
                @foreach($areaUnits as $unit)
                    <option value="{{ $unit }}" {{ old('area_unit', $listing->area_unit) === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-12"><hr class="my-2"></div>

    <div class="col-md-3">
        <label class="form-label">{{ translate('Address') }}</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $listing->address) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ translate('City') }}</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $listing->city) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ translate('State') }}</label>
        <input type="text" name="state" class="form-control" value="{{ old('state', $listing->state) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ translate('Country') }}</label>
        <input type="text" name="country" class="form-control" value="{{ old('country', $listing->country) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ translate('Postal_Code') }}</label>
        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $listing->postal_code) }}">
    </div>

    <div class="col-12"><hr class="my-2"></div>

    <div id="js-house-fields" class="row g-3">
        <div class="col-md-2">
            <label class="form-label">{{ translate('Bedrooms') }}</label>
            <input type="number" min="0" name="bedrooms" class="form-control" value="{{ old('bedrooms', $listing->bedrooms) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ translate('Bathrooms') }}</label>
            <input type="number" min="0" name="bathrooms" class="form-control" value="{{ old('bathrooms', $listing->bathrooms) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ translate('Floors') }}</label>
            <input type="number" min="0" name="floors" class="form-control" value="{{ old('floors', $listing->floors) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ translate('Year_Built') }}</label>
            <input type="number" min="1800" name="year_built" class="form-control" value="{{ old('year_built', $listing->year_built) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ translate('Parking_Spaces') }}</label>
            <input type="number" min="0" name="parking_spaces" class="form-control" value="{{ old('parking_spaces', $listing->parking_spaces) }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" name="furnished" value="1" class="form-check-input" id="js-furnished" {{ old('furnished', $listing->furnished) ? 'checked' : '' }}>
                <label class="form-check-label" for="js-furnished">{{ translate('Furnished') }}</label>
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label d-block">{{ translate('Amenities') }}</label>
        @foreach($amenities as $key => $label)
            <div class="form-check form-check-inline">
                <input type="checkbox" name="amenities[{{ $key }}]" value="1" class="form-check-input" id="js-amenity-{{ $key }}" {{ !empty($selectedAmenities[$key]) ? 'checked' : '' }}>
                <label class="form-check-label" for="js-amenity-{{ $key }}">{{ translate($label) }}</label>
            </div>
        @endforeach
    </div>

    <div class="col-12"><hr class="my-2"></div>

    @if(!empty($listing->images))
        <div class="col-12">
            <label class="form-label d-block">{{ translate('Current_Images') }}</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach($listing->images_full_url as $image)
                    @if($image['path'])
                        <img src="{{ $image['path'] }}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:6px;">
                    @endif
                @endforeach
            </div>
        </div>
    @endif
    <div class="col-12">
        <label class="form-label">{{ translate('Add_Images') }}</label>
        <input type="file" id="js-listing-images" name="images[]" class="form-control" accept="image/*" multiple>
        <div class="form-text text-danger d-none" id="js-listing-images-error"></div>
        <div class="d-flex flex-wrap gap-2 mt-2" id="js-listing-images-preview"></div>
    </div>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('js-listing-type');
        const purposeSelect = document.getElementById('js-purpose');
        const houseFields = document.getElementById('js-house-fields');
        const pricePeriodWrap = document.getElementById('js-price-period-wrap');

        function syncVisibility() {
            houseFields.style.display = typeSelect.value === 'house' ? '' : 'none';
            pricePeriodWrap.style.display = purposeSelect.value === 'rent' ? '' : 'none';
        }

        typeSelect.addEventListener('change', syncVisibility);
        purposeSelect.addEventListener('change', syncVisibility);
        syncVisibility();

        // Client-side preview + a friendly error caught before the file
        // ever leaves the browser — matches the server's own 'image' and
        // 'max:4096' (KB) validation on images.* so a rejected file never
        // has to make a round trip to find out it was rejected.
        const MAX_KB = 4096;
        const imagesInput = document.getElementById('js-listing-images');
        const errorEl = document.getElementById('js-listing-images-error');
        const previewEl = document.getElementById('js-listing-images-preview');

        imagesInput.addEventListener('change', function () {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
            previewEl.innerHTML = '';

            const files = Array.from(imagesInput.files || []);
            const invalid = files.find(function (file) {
                return !file.type.startsWith('image/') || file.size > MAX_KB * 1024;
            });

            if (invalid) {
                imagesInput.value = '';
                errorEl.textContent = !invalid.type.startsWith('image/')
                    ? '{{ translate('Please_choose_image_files_only') }}: ' + invalid.name
                    : '{{ translate('Image_too_large_max') }} ' + MAX_KB / 1024 + 'MB: ' + invalid.name;
                errorEl.classList.remove('d-none');
                return;
            }

            files.forEach(function (file) {
                const img = document.createElement('img');
                img.style.cssText = 'width:90px;height:90px;object-fit:cover;border-radius:6px;';
                img.src = URL.createObjectURL(file);
                previewEl.appendChild(img);
            });
        });
    })();
</script>
