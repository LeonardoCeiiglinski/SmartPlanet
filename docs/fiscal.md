# Preparação fiscal (sem emissão nesta etapa)

O cadastro de produtos armazena NCM, CEST, origem, CFOP e CST/CSOSN. Esses
campos existem para o contador preencher. O sistema **não calcula alíquotas**,
**não assina XML** e **não emite NF-e, NFC-e ou NFS-e**.

O recibo do PDV é identificado como **não fiscal**.

## Pendências obrigatórias antes de emitir

1. Contador responsável pelos parâmetros tributários.
2. Credenciamento na SEFAZ (NF-e/NFC-e) e, se houver serviços, no município
   (NFS-e).
3. Certificado digital ICP-Brasil. A1 e A3 têm fluxos diferentes; A3 exige
   presença do token e, em geral, componente desktop.
4. Série, numeração, CSC/token da NFC-e, RPS/série da NFS-e.
5. Provedor homologado e ambiente de homologação.
6. Contingência e retenção de XML conforme a legislação aplicável.
7. Política de armazenamento de certificado fora do Git e do webroot.

A documentação pública do MarketUP confirma que a emissão fiscal depende de
configuração prévia e que o recibo não fiscal não a substitui. Não há API
pública atual do MarketUP para emitir documentos por este sistema.
