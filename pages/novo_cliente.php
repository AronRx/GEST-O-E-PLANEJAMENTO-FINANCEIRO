<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">Novo Cliente</h2>

            <p class="text-muted mb-0">
                Preencha as informações do Cliente.
            </p>

        </div>

        <a href="index.php?pagina=clientes" class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>

            Voltar

        </a>

    </div>

    <form action="salvar_cliente.php" method="POST">

        <div class="card shadow-sm">

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">CPF</label>
                        <input type="text" name="cpf" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">RG</label>
                        <input type="text" name="rg" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" class="form-control">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Rua</label>
                        <input type="text" name="rua" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <input type="text" name="estado" maxlength="2" class="form-control text-uppercase">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" rows="4" class="form-control"></textarea>
                    </div>

                </div>

            </div>

            <div class="card-footer bg-white d-flex justify-content-end gap-2">

                <a href="index.php?pagina=clientes" class="btn btn-secondary">

                    <i class="bi bi-x-circle"></i>

                    Cancelar

                </a>

                <button type="submit" class="btn btn-success">

                    <i class="bi bi-check-circle"></i>

                    Salvar Cliente

                </button>

            </div>

        </div>

    </form>

</div>