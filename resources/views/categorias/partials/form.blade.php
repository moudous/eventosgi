<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da categoria</h2></div><div class="card-body p-4">
    <div class="row g-4">
        <div class="{{ $permissoes->permite('categorias.ativar_desativar') ? 'col-12 col-lg-9' : 'col-12' }}">
            <label for="nome" class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
            <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" maxlength="255" required autofocus value="{{ old('nome', $categoria?->nome) }}">
            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @if($permissoes->permite('categorias.ativar_desativar'))
        <div class="col-12 col-lg-3">
            <label for="ativo" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
            <select id="ativo" name="ativo" class="form-select @error('ativo') is-invalid @enderror" required>
                <option value="1" @selected((int) old('ativo', isset($categoria) ? (int) $categoria->ativo : 1) === 1)>Ativo</option>
                <option value="0" @selected((int) old('ativo', isset($categoria) ? (int) $categoria->ativo : 1) === 0)>Inativo</option>
            </select>
            @error('ativo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($categoria?->temAtividades())<div class="form-text">Categoria em uso por atividades: não pode ser desativada.</div>@endif
        </div>
        @endif
    </div>
</div></div>
<div class="mt-4 d-flex justify-content-end gap-2">
    <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Salvar</button>
</div>
