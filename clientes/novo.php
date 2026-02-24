<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

// Obter dados do usuário logado
$usuario_logado_id = $_SESSION['usuario_id'] ?? null;
$usuario_logado_nivel = $_SESSION['nivel_autoridade'] ?? null;
?>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Novo Cliente</h3>
            <a href="index.php" class="btn btn-outline-secondary">
                Ver Todos os Clientes
            </a>
        </div>
        <form action="salvar.php" method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" placeholder="Nome completo" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="exemplo@dominio.com">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo Pessoa</label>
                    <select name="tipo_pessoa" class="form-select">
                        <option value="1" selected>Física</option>
                        <option value="2">Jurídica</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Nascimento</label>
                    <input type="text" name="nascimento" class="form-control" placeholder="dd/mm/aaaa">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" placeholder="00000-000">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" placeholder="Rua e número">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" placeholder="Bairro">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control" placeholder="Cidade">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">UF</option>
                        <option value="AC">AC</option>
                        <option value="AL">AL</option>
                        <option value="AP">AP</option>
                        <option value="AM">AM</option>
                        <option value="BA">BA</option>
                        <option value="CE">CE</option>
                        <option value="DF">DF</option>
                        <option value="ES">ES</option>
                        <option value="GO">GO</option>
                        <option value="MA">MA</option>
                        <option value="MT">MT</option>
                        <option value="MS">MS</option>
                        <option value="MG">MG</option>
                        <option value="PA">PA</option>
                        <option value="PB">PB</option>
                        <option value="PR">PR</option>
                        <option value="PE">PE</option>
                        <option value="PI">PI</option>
                        <option value="RJ">RJ</option>
                        <option value="RN">RN</option>
                        <option value="RS">RS</option>
                        <option value="RO">RO</option>
                        <option value="RR">RR</option>
                        <option value="SC">SC</option>
                        <option value="SP">SP</option>
                        <option value="SE">SE</option>
                        <option value="TO">TO</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Ativo">Ativo</option>
                        <option value="Inativo">Inativo</option>
                        <option value="Alerta">Alerta</option>
                        <option value="Atenção">Atenção</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Chave Pix</label>
                    <input type="text" name="chave_pix" class="form-control" placeholder="CPF, telefone, email ou aleatória">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome Secundário</label>
                    <input type="text" name="nome_secundario" class="form-control" placeholder="Contato alternativo">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefone Secundário</label>
                    <input type="text" name="telefone_secundario" class="form-control" placeholder="(00) 00000-0000">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Endereço Secundário</label>
                <input type="text" name="endereco_secundario" class="form-control" placeholder="Ex: onde mora atualmente">
            </div>

            <div class="mb-4">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="4" placeholder="Informações adicionais"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Salvar</button>
        </form>
    </div>
</body>
</html>
