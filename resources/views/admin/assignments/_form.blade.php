<form method="POST" action="{{ $action }}">
    @csrf
    @if ($includeAsset)
        <div class="mb-3">
            <label class="form-label" for="asset_id">Bien <span class="text-danger">*</span></label>
            <select class="form-select @error('asset_id') is-invalid @enderror" id="asset_id" name="asset_id" required>
                <option value="">Sélectionner un bien non affecté</option>
                @foreach ($assets as $option)
                    <option value="{{ $option->id }}" @selected((string) old('asset_id', $selectedAssetId ?? '') === (string) $option->id)>{{ $option->asset_code }} — {{ $option->designation }}</option>
                @endforeach
            </select>
            @error('asset_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
    <div class="mb-3">
        <label class="form-label" for="agent_id">Agent</label>
        <select class="form-select @error('agent_id') is-invalid @enderror" id="agent_id" name="agent_id">
            <option value="">Aucun agent</option>
            @foreach ($agents as $agent)
                <option value="{{ $agent->id }}" @selected((string) old('agent_id') === (string) $agent->id)>{{ $agent->last_name }} {{ $agent->first_name }}{{ $agent->service ? ' — '.$agent->service->name : '' }}</option>
            @endforeach
        </select>
        @error('agent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="service_id">Service</label>
        <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id">
            <option value="">Aucun service</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->name }}</option>
            @endforeach
        </select>
        @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Choisir exactement un agent ou un service.</div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="assigned_at">Date de prise en charge / transfert</label>
        <input class="form-control @error('assigned_at') is-invalid @enderror" id="assigned_at" name="assigned_at" type="datetime-local" value="{{ old('assigned_at', now()->format('Y-m-d\TH:i')) }}" required>
        @error('assigned_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="reason">Motif</label>
        <input class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" value="{{ old('reason') }}" maxlength="120" required>
        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="observations">Observations</label>
        <textarea class="form-control @error('observations') is-invalid @enderror" id="observations" name="observations" rows="3" maxlength="5000">{{ old('observations') }}</textarea>
        @error('observations')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</form>
