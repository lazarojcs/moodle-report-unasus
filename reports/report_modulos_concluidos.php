<?php

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');


class report_modulos_concluidos extends Factory {

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
        $this->mostrar_botao_exportar_csv = false;
    }

    public function render_report_default($renderer){
        echo $renderer->build_page();
    }

    public function render_report_table($renderer){
        $this->mostrar_barra_filtragem = false;
        $this->texto_cabecalho = 'Tutores';
        echo $renderer->build_report($this);
    }

    public function get_dados(){

        $modulos = $this->modulos_selecionados;

        // Consulta
        $query_atividades_nao_postadas = query_atividades();
        $query_quiz = query_quiz();
        $query_forum = query_postagens_forum();

        // Recupera dados auxiliares
        $nomes_cohorts = get_nomes_cohorts($this->get_categoria_curso_ufsc());
        $nomes_estudantes = grupos_tutoria::get_estudantes($this->get_categoria_turma_ufsc());
        $nomes_polos = get_polos($this->get_categoria_turma_ufsc());

        $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($query_atividades_nao_postadas, $query_forum, $query_quiz);

        $dados = array();

        foreach ($associativo_atividades as $grupo_id => $array_dados) {

            $estudantes = array();
            foreach ($array_dados as $id_aluno => $aluno) {
                $dados_modulos = array();

                $lista_atividades[] = new report_unasus_student($nomes_estudantes[$id_aluno], $id_aluno, $this->get_curso_moodle(), $aluno[0]->polo, $aluno[0]->cohort);

                foreach ($aluno as $atividade) {
                    /** @var report_unasus_data $atividade */

                    $full_grade[$id_aluno] = grade_get_course_grade($id_aluno, $atividade->source_activity->course_id);

                    if(isset($full_grade[$id_aluno])){
                        $final_grade = $full_grade[$id_aluno]->str_grade;
                    } else if(empty($final_grade)){
                        $final_grade = 'Não há atividades avaliativas para o módulo';
                    }

                    // para cada novo modulo ele cria uma entrada de dado com o número de atividades daquele modulo
                    if (!array_key_exists($atividade->source_activity->course_id, $dados_modulos)) {
                        $dados_modulos[$atividade->source_activity->course_id] = new dado_modulos_concluidos(sizeof($modulos), $final_grade, $atividade);
                    }

                    // para cada atividade nao feita ele adiciona uma nova atividade nao realizada naquele modulo
                    if ($atividade->source_activity->has_submission()
                            && !$atividade->has_submitted()
                            && !$atividade->is_a_future_due()
                            && !$atividade->has_grade()
                            && $atividade->is_activity_pending()
                            && $atividade->is_member_of($this->agrupamentos_membros))
                    {
                        $dados_modulos[$atividade->source_activity->course_id]->add_atividade_nao_realizada();
                        $dados_modulos[$atividade->source_activity->course_id]->add_atividades_pendentes($atividade->source_activity);
                    }
                }

                $atividades_nao_realizadas_do_estudante = 0;
                foreach ($dados_modulos as $key => $modulo) {
                    $lista_atividades[] = $modulo;
                    $atividades_nao_realizadas_do_estudante += $modulo->get_total_atividades_nao_realizadas();
                }

                $estudantes[] = $lista_atividades;

                // Unir os alunos de acordo com o polo deles
                if ($this->agrupar_relatorios == AGRUPAR_POLOS) {
                    $dados[$nomes_polos[$lista_atividades[0]->polo]][] = $lista_atividades;
                }
                // Unir os alunos de acordo com o cohort deles
                if ($this->agrupar_relatorios == AGRUPAR_COHORTS) {
                    $key = isset($lista_atividades[0]->cohort) ? $nomes_cohorts[$lista_atividades[0]->cohort] : get_string('cohort_empty', 'report_unasus');
                    $dados[$key][] = $lista_atividades;
                }
                $lista_atividades = null;
            }

            // Ou unir os alunos de acordo com o tutor dele
            if ($this->agrupar_relatorios == AGRUPAR_TUTORES) {
                $dados[grupos_tutoria::grupo_tutoria_to_string($this->get_categoria_turma_ufsc(), $grupo_id)] = $estudantes;
            }
        }

        return $dados;
    }

    public function get_table_header(){

        $activities = $this->get_table_header_modulos_atividades();

        $header = array();
        $header[] = 'Estudantes';
        foreach ($activities as $h) {
            $header[] = new dado_modulo($h[0]->course_id, $h[0]->course_name);
        }

        return $header;
    }
}