<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\View;

final class ProcedureController
{
    public function index(): void
    {
        Auth::authorize('procedures.view');
        View::render('procedures/index', [
            'title' => 'Procedimentos',
            'procedures' => self::catalog(),
        ]);
    }

    /** @return array<int, array{id:string,menu:string,objetivo:string,passos:array<int,string>,regra:string}> */
    public static function catalog(): array
    {
        return [
            [
                'id' => 'painel',
                'menu' => 'Painel',
                'objetivo' => 'Ver o dia da SmartPlanet assim que entrar.',
                'passos' => [
                    'Abra o Painel depois do login.',
                    'Confira vendas de hoje, total, ticket médio e se há caixa aberto.',
                    'No bloco Vendas do período, filtre as datas e use PDF para ver o que mais vende e o que mais é procurado.',
                    'Se estoque baixo, a receber ou a pagar aparecerem, siga para Estoque, Financeiro ou Relatórios.',
                ],
                'regra' => 'Os números saem dos registros do banco. O painel não emite nota fiscal.',
            ],
            [
                'id' => 'pdv',
                'menu' => 'PDV',
                'objetivo' => 'Registrar a venda na frente de loja com o leitor de código de barras.',
                'passos' => [
                    'Confirme que o caixa da filial está aberto.',
                    'Deixe o cursor no campo Leia o código e pressione Enter. F2 ou Esc devolvem o foco para esse campo.',
                    'Bipe a etiqueta. O produto entra no carrinho. Outro bip soma uma unidade.',
                    'Escolha o cliente só quando não for consumidor final.',
                    'Informe o pagamento. PIX e cartões precisam da confirmação operacional.',
                    'Conclua com F9. Para atender outra pessoa no meio, suspenda com F4 e recupere depois.',
                ],
                'regra' => 'A venda só conclui com caixa aberto. Concluir duas vezes não baixa o estoque duas vezes. O recibo é não fiscal.',
            ],
            [
                'id' => 'produtos',
                'menu' => 'Produtos e serviços',
                'objetivo' => 'Manter o cadastro que o caixa consulta.',
                'passos' => [
                    'Pesquise por nome, código interno ou código de barras antes de criar um item novo.',
                    'Preencha descrição, unidade e preço de venda. Produto físico pode controlar estoque e ter saldo inicial.',
                    'Grave o código de barras exatamente como ele será impresso na etiqueta.',
                    'Use Importar para uma planilha conferida, ou Exportar CSV para levar a lista.',
                    'Inative o item que saiu de linha. Não apague o que já foi vendido.',
                ],
                'regra' => 'Código de barras é único. Serviço não movimenta estoque. Campos fiscais ficam para o contador e não emitem nota.',
            ],
            [
                'id' => 'etiquetas',
                'menu' => 'Etiquetas',
                'objetivo' => 'Imprimir a bobina 33 × 21 mm, em 3 colunas, na 4B-2054N.',
                'passos' => [
                    'Cadastre na tela de etiquetas o nome, o preço, a quantidade e o mesmo código do produto do PDV.',
                    'Confira a prévia: 3 etiquetas formam uma fileira; 12 etiquetas formam quatro fileiras.',
                    'Conecte a impressora por USB, calibre o sensor uma vez por bobina e imprima a fileira de teste.',
                    'Só depois use Imprimir etiquetas e confirme na janela. Nada sai sem essa confirmação.',
                    'No balcão de etiquetas, o campo Bipe a etiqueta registra o produto que já está nessa lista.',
                ],
                'regra' => 'A lista de impressão fica neste navegador. O PDV só reconhece o bip se o código impresso existir no cadastro de produtos.',
            ],
            [
                'id' => 'estoque',
                'menu' => 'Estoque',
                'objetivo' => 'Saber a quantidade da filial e corrigir com rastro.',
                'passos' => [
                    'Consulte o saldo e o que está no mínimo ou abaixo.',
                    'Para corrigir, escolha produto, local, quantidade com sinal e uma justificativa de pelo menos cinco caracteres.',
                    'Para contar a loja, inicie a contagem no local, lance o que foi contado e feche o inventário.',
                    'Abra o histórico quando precisar saber quem movimentou e por quê.',
                ],
                'regra' => 'Ajuste e inventário somam um movimento novo. O saldo anterior não é reescrito.',
            ],
            [
                'id' => 'compras',
                'menu' => 'Compras',
                'objetivo' => 'Pedir mercadoria ao fornecedor e dar entrada quando ela chega.',
                'passos' => [
                    'Cadastre o fornecedor antes, se ele ainda não existir.',
                    'Crie o pedido com produtos, quantidade e custo.',
                    'No recebimento, informe o local e a quantidade que chegou. Pode ser menor que a pedida.',
                    'Confira o saldo em Estoque depois da entrada.',
                ],
                'regra' => 'Receber o mesmo lançamento outra vez não duplica o estoque.',
            ],
            [
                'id' => 'vendas',
                'menu' => 'Vendas',
                'objetivo' => 'Consultar o que foi vendido, imprimir o recibo e corrigir com autorização.',
                'passos' => [
                    'Abra a venda pelo número. Marque a via do cliente, a da loja ou as duas. Cada cupom sai com o nome da via. Imprimir solta só as vias marcadas, em papel de 58 mm.',
                    'Cancelar exige justificativa e devolve o estoque uma vez.',
                    'Na devolução, marque só a quantidade ainda não devolvida e se a peça volta ao estoque.',
                    'Estorno em dinheiro só ocorre com o caixa aberto.',
                ],
                'regra' => 'Operador de caixa consulta a venda. Cancelar e devolver são atos de gerente ou do dono.',
            ],
            [
                'id' => 'caixa',
                'menu' => 'Caixa',
                'objetivo' => 'Controlar o dinheiro físico do turno.',
                'passos' => [
                    'Abra o caixa com o fundo de troco antes da primeira venda.',
                    'Suprimento coloca dinheiro; sangria retira. Os dois pedem valor e justificativa.',
                    'No fim do turno, conte o dinheiro e feche. Se o valor divergir, explique a diferença.',
                ],
                'regra' => 'Há um caixa aberto por vez na filial. Caixa não é a mesma coisa que conta a receber ou a pagar.',
            ],
            [
                'id' => 'clientes',
                'menu' => 'Clientes',
                'objetivo' => 'Identificar quem compra quando a venda não é de consumidor final.',
                'passos' => [
                    'Pesquise o nome ou o documento antes de cadastrar de novo.',
                    'Grave nome e, se houver, documento, e-mail e telefone.',
                    'Inative quem não deve mais aparecer no PDV. As vendas antigas permanecem.',
                ],
                'regra' => 'Cliente ativo pode ser escolhido no PDV, no orçamento e numa conta a receber.',
            ],
            [
                'id' => 'fornecedores',
                'menu' => 'Fornecedores',
                'objetivo' => 'Identificar quem abastece a boutique.',
                'passos' => [
                    'Cadastre o fornecedor antes do pedido de compra.',
                    'Use o mesmo cadastro no produto, quando houver fornecedor principal, e na conta a pagar.',
                ],
                'regra' => 'Inativar o fornecedor não apaga compras já recebidas.',
            ],
            [
                'id' => 'financeiro',
                'menu' => 'Financeiro',
                'objetivo' => 'Acompanhar contas a receber e a pagar.',
                'passos' => [
                    'Lance a receita ou a despesa com descrição, valor, emissão e vencimento.',
                    'Na liquidação, escolha a conta financeira e o valor pago ou recebido. Pode ser parcial.',
                    'Consulte o saldo em aberto no próprio menu e no Painel.',
                ],
                'regra' => 'Liquidar uma conta não substitui a abertura e o fechamento do caixa.',
            ],
            [
                'id' => 'orcamentos',
                'menu' => 'Orçamentos',
                'objetivo' => 'Registrar uma proposta antes de vender.',
                'passos' => [
                    'Escolha o cliente ou deixe consumidor final, a validade e os produtos.',
                    'Salve. O orçamento não baixa estoque.',
                    'Baixe o PDF em folha A4 para enviar ao cliente.',
                    'Quando o cliente fechar, converta em venda uma vez, com a forma de pagamento.',
                ],
                'regra' => 'A conversão usa o mesmo cuidado da venda: estoque e pagamento são gravados naquele momento.',
            ],
            [
                'id' => 'relatorios',
                'menu' => 'Relatórios',
                'objetivo' => 'Ler o período para o dono acompanhar a loja.',
                'passos' => [
                    'Informe a data inicial e a final e filtre.',
                    'Leia vendas por dia, produtos, operadores, pagamentos, estoque baixo, devoluções e fechamentos.',
                    'Exporte o CSV das vendas por dia quando precisar da planilha.',
                ],
                'regra' => 'O relatório mostra o que foi gravado. Não calcula imposto e não substitui nota.',
            ],
            [
                'id' => 'funcionarios',
                'menu' => 'Funcionários',
                'objetivo' => 'O dono da loja liberar o acesso de quem trabalha.',
                'passos' => [
                    'Entre com o login de administrador master.',
                    'Abra Funcionários e escolha Novo funcionário.',
                    'Defina nome, e-mail, senha inicial, perfil Gerente ou Operador de caixa, e a filial.',
                    'Entregue o e-mail e a senha ao funcionário. Para bloquear o acesso, edite e desmarque Login ativo.',
                ],
                'regra' => 'Funcionário não cria login, não se promove a dono e não vê este menu. O administrador master não é inativado por esta tela.',
            ],
            [
                'id' => 'configuracoes',
                'menu' => 'Configurações',
                'objetivo' => 'Guardar os dados da SmartPlanet e as listas do cadastro.',
                'passos' => [
                    'Atualize razão social, nome fantasia, CNPJ, telefone e e-mail.',
                    'Inclua categorias, marcas e unidades antes de usar essas opções no produto.',
                ],
                'regra' => 'A instalação já traz unidades UN, KG, LT e SV, o caixa principal e as formas dinheiro, PIX, débito e crédito.',
            ],
            [
                'id' => 'auditoria',
                'menu' => 'Auditoria',
                'objetivo' => 'Ver quem fez cada operação.',
                'passos' => [
                    'Abra a Auditoria quando precisar conferir cadastro, estoque, venda, caixa ou financeiro.',
                    'Use cancelamento, devolução ou ajuste para corrigir. Não apague movimento para acertar número.',
                ],
                'regra' => 'O registro permanece mesmo depois que o funcionário é inativado.',
            ],
        ];
    }
}
