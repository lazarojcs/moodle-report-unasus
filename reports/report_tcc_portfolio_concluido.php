<?php

class report_tcc_portfolio_concluido extends Factory{

    protected function initialize() {
        $this->mostrar_filtro_tutores = true;
        $this->mostrar_barra_filtragem = true;
        $this->mostrar_botoes_grafico = false;
        $this->mostrar_botoes_dot_chart = false;
        $this->mostrar_filtro_polos = true;
        $this->mostrar_filtro_cohorts = true;
        $this->mostrar_filtro_modulos = true;
        $this->mostrar_filtro_intervalo_tempo = false;
        $this->mostrar_aviso_intervalo_tempo = false;
    }

    public function render_report_default($renderer){
        echo $renderer->build_page();
    }

    public function render_report_table($renderer, $report) {
        $this->mostrar_barra_filtragem = false;
        echo $renderer->build_report($report);
    }

    public function get_dados(){
        // Recupera dados auxiliares
        $nomes_cohorts = get_nomes_cohorts($this->get_curso_ufsc());
        $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($this->get_curso_ufsc());
        $nomes_polos = get_polos($this->get_curso_ufsc());

        /*  associativo_atividades[modulo][id_aluno][atividade]
         *
         * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
         */
        $associativo_atividades = loop_atividades_e_foruns_de_um_modulo(null, null, null);

        $dados = array();
        foreach ($associativo_atividades as $grupo_id => $array_dados) {
            $estudantes = array();

            foreach ($array_dados as $id_aluno => $aluno) {
                $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $this->get_curso_moodle(), $aluno[0]->polo, $aluno[0]->cohort);
                foreach ($aluno as $atividade) {
                    /** @var report_unasus_data $atividade */
                    $atraso = null;

                    if ($atividade instanceof report_unasus_data_empty) {
                        $lista_atividades[] = new dado_nao_aplicado();
                        continue;
                    }

                    // Se a atividade não foi entregue
                    if ($atividade->has_evaluated()) {
                        // E não tem entrega prazo
                        $tipo = dado_tcc_portfolio_concluido::ATIVIDADE_CONCLUIDA;
                    } else {
                        //atividade com data de entrega no futuro, nao entregue mas dentro do prazo
                        $tipo = dado_tcc_portfolio_concluido::ATIVIDADE_NAO_CONCLUIDA;
                    }
                    $lista_atividades[] = new dado_tcc_portfolio_concluido($tipo, $atividade->source_activity->id, $atraso);
                }
                $estudantes[] = $lista_atividades;
                // Unir os alunos de acordo com o polo deles
                if ($this->agrupar_relatorios == AGRUPAR_POLOS) {
                    $dados[$nomes_polos[$lista_atividades[0]->polo]][] = $lista_atividades;
                }
                // Unir os alunos de acordo com o cohort deles
                if ($this->agrupar_relatorios == AGRUPAR_COHORTS) {
                    $key = isset($lista_atividades[0]->cohort) ? $nomes_cohorts[$lista_atividades[0]->cohort] : REPORT_UNASUS_COHORT_EMPTY;
                    $dados[$key][] = $lista_atividades;
                }
                $lista_atividades = null;
            }
            // Ou unir os alunos de acordo com o tutor dele
            if ($this->agrupar_relatorios == AGRUPAR_TUTORES) {
                $dados[grupos_tutoria::grupo_tutoria_to_string($this->get_curso_ufsc(), $grupo_id)] = $estudantes;
            }
        }

        return ($dados);
    }

    public function get_table_header(){
        return $this->get_table_header_tcc_portfolio_entrega_atividades();
    }

} 