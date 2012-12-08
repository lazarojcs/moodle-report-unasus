<?php

require_once($CFG->dirroot . '/report/unasus/relatorios/queries.php');
require_once($CFG->dirroot . '/report/unasus/relatorios/loops.php');

defined('MOODLE_INTERNAL') || die;


/* -----------------
 * ---------------------------------------
 * Relatório de Atividades vs Notas Atribuídas
 * ---------------------------------------
 * -----------------
 */


/**
 * Geração de dados dos tutores e seus respectivos alunos.
 *
 * @param $modulos
 * @param $tutores
 * @param $curso_ufsc
 * @param $curso_moodle
 * @return array Array[tutores][aluno][unasus_data]
 */
function get_dados_atividades_vs_notas($curso_ufsc, $curso_moodle, $modulos, $tutores)
{
    // Dado Auxiliar
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);

    // Consultas
    $query_alunos_grupo_tutoria = query_atividades_vs_notas();
    $query_forum = query_postagens_forum();


    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
                                                                    $modulos,$tutores,
                                                                    $query_alunos_grupo_tutoria,$query_forum);



    //pega a hora atual para comparar se uma atividade esta atrasada ou nao
    $timenow = time();


    $dados = array();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {
        $estudantes = array();
        foreach ($array_dados as $id_aluno => $aluno) {
            $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $curso_moodle);


            foreach ($aluno as $atividade) {
                $atraso = null;

                // Não entregou
                if (is_null($atividade->submission_date)) {
                    if ($atividade->duedate == 0) {
                        $tipo = dado_atividades_vs_notas::ATIVIDADE_SEM_PRAZO_ENTREGA;
                    } elseif ($atividade->duedate > $timenow) {
                        $tipo = dado_atividades_vs_notas::ATIVIDADE_NO_PRAZO_ENTREGA;
                    } else {
                        $tipo = dado_atividades_vs_notas::ATIVIDADE_NAO_ENTREGUE;
                    }
                } // Entregou e ainda não foi avaliada
                elseif (is_null($atividade->grade) || $atividade->grade < 0) {
                    $tipo = dado_atividades_vs_notas::CORRECAO_ATRASADA;

                    // calculo do atraso
                    $submission_date = ((int)$atividade->submission_date == 0) ? $atividade->timemodified : $atividade->submission_date;
                    $datadiff = date_create()->diff(get_datetime_from_unixtime($submission_date));
                    $atraso = (int)$datadiff->format("%a");
                } // Atividade entregue e avaliada
                elseif ($atividade->grade > -1) {
                    $tipo = dado_atividades_vs_notas::ATIVIDADE_AVALIADA;
                } else {
                    print_error('unmatched_condition', 'report_unasus');
                }

                $lista_atividades[] = new dado_atividades_vs_notas($tipo, $atividade->assignid, $atividade->grade, $atraso);
            }
            $estudantes[] = $lista_atividades;
            $lista_atividades = null;
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = $estudantes;
    }

    return $dados;
}

/**
 *  Cabeçalho de duas linhas para os relatórios
 *  Primeira linha módulo1, modulo2
 *  Segunda linha ativ1_mod1, ativ2_mod1, ativ1_mod2, ativ2_mod2
 *
 * @param array $modulos
 * @return array
 */
function get_table_header_atividades_vs_notas($modulos = array())
{
    $atividades_modulos = query_atividades_modulos($modulos);
    $foruns_modulos = query_forum_modulo($modulos);



    $group = new GroupArray();
    $modulos = array();
    $header = array();

    // Agrupa atividades por curso e cria um índice de cursos
    foreach ($atividades_modulos as $atividade) {
        $modulos[$atividade->course_id] = $atividade->course_name;
        $group->add($atividade->course_id, $atividade);
    }

    //Agrupa os foruns pelos seus respectivos modulos
    foreach ($foruns_modulos as $forum) {
        $group->add($forum->course_id, $forum);
    }

    $group_assoc = $group->get_assoc();

    foreach ($group_assoc as $course_id => $atividades) {
        $course_url = new moodle_url('/course/view.php', array('id' => $course_id));
        $course_link = html_writer::link($course_url, $modulos[$course_id]);
        $dados = array();

        foreach ($atividades as $atividade) {
            if (array_key_exists('assign_id', $atividade)) {
                $cm = get_coursemodule_from_instance('assign', $atividade->assign_id, $course_id, null, MUST_EXIST);

                $atividade_url = new moodle_url('/mod/assign/view.php', array('id' => $cm->id));
                $dados[] = html_writer::link($atividade_url, $atividade->assign_name);
            } else {
                $forum_url = new moodle_url('/mod/forum/view.php', array('id' => $atividade->idnumber));
                $dados[] = html_writer::link($forum_url, $atividade->itemname);
            }
        }
        $header[$course_link] = $dados;
    }
    return $header;
}


function get_dados_grafico_atividades_vs_notas($curso_ufsc, $modulos, $tutores)
{
    global $CFG;

    // Consultas
    $query_alunos_grupo_tutoria = query_atividades_vs_notas();
    $query_forum = query_postagens_forum();


    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
                                                                    $modulos,$tutores,
                                                                    $query_alunos_grupo_tutoria,$query_forum);

    //pega a hora atual para comparar se uma atividade esta atrasada ou nao
    $timenow = time();


//  Ordem dos dados nos gráficos
//        'nota_atribuida'
//        'pouco_atraso'
//        'muito_atraso'
//        'nao_entregue'
//        'nao_realizada'
//        'sem_prazo'

    $dados = array();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {
        //variáveis soltas para melhor entendimento
        $count_nota_atribuida = 0;
        $count_pouco_atraso = 0;
        $count_muito_atraso = 0;
        $count_nao_entregue = 0;
        $count_nao_realizada = 0;
        $count_sem_prazo = 0;

        foreach ($array_dados as $id_aluno => $aluno) {
            foreach ($aluno as $atividade) {

                $atraso = null;

                // Não entregou
                if (is_null($atividade->submission_date)) {
                    if ((int)$atividade->duedate == 0) {
                        $count_sem_prazo++;
                    } elseif ($atividade->duedate > $timenow) {
                        $count_nao_realizada++;
                    } else {
                        $count_nao_entregue++;
                    }
                } // Entregou e ainda não foi avaliada
                elseif (is_null($atividade->grade) || (float)$atividade->grade < 0) {
                    $tipo = dado_atividades_vs_notas::CORRECAO_ATRASADA;
                    $submission_date = ((int)$atividade->submission_date == 0) ? $atividade->timemodified : $atividade->submission_date;
                    $datadiff = date_create()->diff(get_datetime_from_unixtime($submission_date));
                    $atraso = $datadiff->format("%a");
                    ($atraso > $CFG->report_unasus_prazo_maximo_avaliacao) ? $count_muito_atraso++ : $count_pouco_atraso++;
                } // Atividade entregue e avaliada
                elseif ((float)$atividade->grade > -1) {
                    $count_nota_atribuida++;
                } else {
                    print_error('unmatched_condition', 'report_unasus');
                }
            }
        }

        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] =
            array($count_nota_atribuida,
                $count_pouco_atraso,
                $count_muito_atraso,
                $count_nao_entregue,
                $count_nao_realizada,
                $count_sem_prazo);


    }
    return $dados;
}

/* -----------------
 * ---------------------------------------
 * Relatório de Acompanhamento de Entrega de Atividades
 * ---------------------------------------
 * -----------------
 */


/**
 * Geração de dados dos tutores e seus respectivos alunos.
 *
 * @param string $curso_ufsc
 * @param string $curso_moodle
 * @param array $modulos
 * @param array $tutores
 * @return array Array[tutores][aluno][unasus_data]
 */
function get_dados_entrega_de_atividades($curso_ufsc, $curso_moodle, $modulos, $tutores) {
    global $CFG;

    // Consultas
    $query_alunos_grupo_tutoria = query_entrega_de_atividades();
    $query_forum = query_postagens_forum();

    // Recupera dados auxiliares
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);

    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
                                                                    $modulos,$tutores,
                                                                    $query_alunos_grupo_tutoria,$query_forum);

    $dados = array();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {
        $estudantes = array();
        foreach ($array_dados as $id_aluno => $aluno) {
            $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $curso_moodle);

            foreach ($aluno as $atividade) {
                $atraso = null;

                // Não enviou a atividade
                if (is_null($atividade->submission_date)) {
                    if ((int) $atividade->duedate == 0) {
                        // Não entregou e Atividade sem prazo de entrega
                        $tipo = dado_entrega_de_atividades::ATIVIDADE_SEM_PRAZO_ENTREGA;
                    } else {
                        // Não entregou e fora do prazo
                        $tipo = dado_entrega_de_atividades::ATIVIDADE_NAO_ENTREGUE;
                    }
                }
                // Entregou antes ou na data de entrega esperada que é a data de entrega com uma tolencia de $CFG->report_unasus_prazo_entrega dias
                elseif ((int) $atividade->submission_date <= (int) ($atividade->duedate + (3600 * 24 * $CFG->report_unasus_prazo_entrega))) {
                    $tipo = dado_entrega_de_atividades::ATIVIDADE_ENTREGUE_NO_PRAZO;
                }
                // Entregou após a data esperada
                else {
                    $tipo = dado_entrega_de_atividades::ATIVIDADE_ENTREGUE_FORA_DO_PRAZO;
                    $submission_date = ((int) $atividade->submission_date == 0) ? $atividade->timemodified : $atividade->submission_date;
                    $datadiff = get_datetime_from_unixtime($submission_date)->diff(get_datetime_from_unixtime($atividade->duedate));
                    $atraso = $datadiff->format("%a");
                }
                $lista_atividades[] = new dado_entrega_de_atividades($tipo, $atividade->assignid, $atraso);
            }
            $estudantes[] = $lista_atividades;
            $lista_atividades = null;
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = $estudantes;
    }

    return($dados);
}

/*
 * Cabeçalho da tabela
 */
function get_table_header_entrega_de_atividades($modulos) {
    return get_table_header_atividades_vs_notas($modulos);
}

/*
 * Dados para o gráfico do relatorio entrega de atividadas
 */
function get_dados_grafico_entrega_de_atividades($curso_ufsc, $modulos, $tutores) {
    global $CFG;

    // Consultas
    $query_alunos_grupo_tutoria = query_entrega_de_atividades();
    $query_forum = query_postagens_forum();


    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
                                                                    $modulos,$tutores,
                                                                    $query_alunos_grupo_tutoria,$query_forum);


    $dados = array();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {
        //variáveis soltas para melhor entendimento
        $count_entregue_no_prazo = 0;
        $count_pouco_atraso = 0;
        $count_muito_atraso = 0;
        $count_nao_entregue = 0;
        $count_sem_prazo = 0;


        foreach ($array_dados as $id_aluno => $aluno) {

            foreach ($aluno as $atividade) {
                $atraso = null;

                // Não enviou a atividade
                if (is_null($atividade->submission_date)) {
                    if ((int) $atividade->duedate == 0) {
                        // Não entregou e Atividade sem prazo de entrega
                        $count_sem_prazo++;
                    } else {
                        // Não entregou e fora do prazo
                        $count_nao_entregue++;
                    }
                }
                // Entregou antes ou na data de entrega esperada que é a data de entrega com uma tolencia de $CFG->report_unasus_prazo_entrega dias
                elseif ((int) $atividade->submission_date <= (int) ($atividade->duedate + (3600 * 24 * $CFG->report_unasus_prazo_entrega))) {
                    $count_entregue_no_prazo++;
                }
                // Entregou após a data esperada
                else {
                    $tipo = dado_entrega_de_atividades::ATIVIDADE_ENTREGUE_FORA_DO_PRAZO;
                    $submission_date = ((int) $atividade->submission_date == 0) ? $atividade->timemodified : $atividade->submission_date;
                    $datadiff = get_datetime_from_unixtime($submission_date)->diff(get_datetime_from_unixtime($atividade->duedate));
                    $atraso = $datadiff->format("%a");
                    ($atraso > $CFG->report_unasus_prazo_maximo_avaliacao) ? $count_muito_atraso++ : $count_pouco_atraso++;
                }
            }
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] =
            array($count_nao_entregue,
                $count_sem_prazo,
                $count_entregue_no_prazo,
                $count_pouco_atraso,
                $count_muito_atraso,
            );
        ;
    }

    return($dados);
}

/* -----------------
 * ---------------------------------------
 * Relatório de Histórico de Atribuição de Notas
 * ---------------------------------------
 * -----------------
 */

/**
 * Geração de dados dos tutores e seus respectivos alunos.
 *
 * @param string $curso_ufsc
 * @param string $curso_moodle
 * @param array $modulos
 * @param array $tutores
 * @return array|bool Array[tutores][aluno][unasus_data]
 */
function get_dados_historico_atribuicao_notas($curso_ufsc, $curso_moodle, $modulos, $tutores) {
    global $CFG;

    // Consultas
    $query_alunos_grupo_tutoria = query_entrega_de_atividades();
    $query_forum = query_postagens_forum();

    // Recupera dados auxiliares
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);

    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
                                                                    $modulos,$tutores,
                                                                    $query_alunos_grupo_tutoria,$query_forum);

    $dados = array();
    $timenow = time();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {
        $estudantes = array();
        foreach ($array_dados as $id_aluno => $aluno) {
            $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $curso_moodle);

            foreach ($aluno as $atividade) {
                $atraso = null;
                // Não enviou a atividade
                if (is_null($atividade->submission_date)) {
                    $tipo = dado_historico_atribuicao_notas::ATIVIDADE_NAO_ENTREGUE;
                } //Atividade entregue e não avaliada
                elseif (is_null($atividade->grade) || $atividade->grade < 0) {

                    $tipo = dado_historico_atribuicao_notas::ATIVIDADE_ENTREGUE_NAO_AVALIADA;
                    $data_envio = ((int) $atividade->submission_date != 0) ? $atividade->submission_date : $atividade->submission_modified;
                    $datadiff = get_datetime_from_unixtime($timenow)->diff(get_datetime_from_unixtime($data_envio));
                    $atraso = (int) $datadiff->format("%a");
                } //Atividade entregue e avalidada
                elseif ((int) $atividade->grade >= 0) {

                    //quanto tempo desde a entrega até a correção
                    $data_correcao = ((int) $atividade->grade_created != 0) ? $atividade->grade_created : $atividade->grade_modified;
                    $data_envio = ((int) $atividade->submission_date != 0) ? $atividade->submission_date : $atividade->submission_modified;
                    $datadiff = get_datetime_from_unixtime($data_correcao)->diff(get_datetime_from_unixtime($data_envio));
                    $atraso = (int) $datadiff->format("%a");

                    //Correção no prazo esperado
                    if ($atraso <= $CFG->report_unasus_prazo_avaliacao) {
                        $tipo = dado_historico_atribuicao_notas::CORRECAO_NO_PRAZO;
                    } //Correção com pouco atraso
                    elseif ($atraso <= $CFG->report_unasus_prazo_maximo_avaliacao) {
                        $tipo = dado_historico_atribuicao_notas::CORRECAO_POUCO_ATRASO;
                    } //Correção com muito atraso
                    else {
                        $tipo = dado_historico_atribuicao_notas::CORRECAO_MUITO_ATRASO;
                    }
                } else {
                    print_error('unmatched_condition', 'report_unasus');
                    return false;
                }

                $lista_atividades[] = new dado_historico_atribuicao_notas($tipo, $atividade->assignid, $atraso);
            }
            $estudantes[] = $lista_atividades;
            $lista_atividades = null;
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = $estudantes;
    }

    return $dados;
}

/*
 * Cabeçalho do relatorio historico atribuicao de notas
 */
function get_table_header_historico_atribuicao_notas($modulos) {
    return get_table_header_atividades_vs_notas($modulos);
}

/*
 * Dados para o gráfico de historico atribuicao de notas
 */
function get_dados_grafico_historico_atribuicao_notas($curso_ufsc, $modulos, $tutores) {
    global $CFG;

    // Consultas
    $query_alunos_grupo_tutoria = query_entrega_de_atividades();
    $query_forum = query_postagens_forum();

    // Recupera dados auxiliares
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);

    /*  associativo_atividades[modulo][id_aluno][atividade]
     *
     * Para cada módulo ele lista os alunos com suas respectivas atividades (atividades e foruns com avaliação)
     */
    $associativo_atividades = loop_atividades_e_foruns_de_um_modulo($curso_ufsc,
        $modulos,$tutores,
        $query_alunos_grupo_tutoria,$query_forum);

    $dados = array();
    foreach ($associativo_atividades as $grupo_id => $array_dados) {

        $count_nao_entregue = 0;
        $count_nao_avaliada = 0;
        $count_no_prazo = 0;
        $count_pouco_atraso = 0;
        $count_muito_atraso = 0;

        foreach ($array_dados as $id_aluno => $aluno) {

            foreach ($aluno as $atividade) {
                $atraso = null;
                // Não enviou a atividade
                if (is_null($atividade->submission_date)) {
                    $count_nao_entregue++;
                } //Atividade entregue e não avaliada
                elseif (is_null($atividade->grade) || $atividade->grade < 0) {
                    $count_nao_avaliada++;
                } //Atividade entregue e avalidada
                elseif ((int) $atividade->grade >= 0) {

                    //quanto tempo desde a entrega até a correção
                    $data_correcao = ((int) $atividade->grade_created != 0) ? $atividade->grade_created : $atividade->grade_modified;
                    $data_envio = ((int) $atividade->submission_date != 0) ? $atividade->submission_date : $atividade->submission_modified;
                    $datadiff = get_datetime_from_unixtime($data_correcao)->diff(get_datetime_from_unixtime($data_envio));
                    $atraso = (int) $datadiff->format("%a");

                    //Correção no prazo esperado
                    if ($atraso <= $CFG->report_unasus_prazo_avaliacao) {
                        $count_no_prazo++;
                    } //Correção com pouco atraso
                    elseif ($atraso <= $CFG->report_unasus_prazo_maximo_avaliacao) {
                        $count_pouco_atraso++;
                    } //Correção com muito atraso
                    else {
                        $count_muito_atraso++;
                    }
                } else {
                    print_error('unmatched_condition', 'report_unasus');
                    return false;
                }
            }
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = array(
            $count_nao_entregue,
            $count_nao_avaliada,
            $count_no_prazo,
            $count_pouco_atraso,
            $count_muito_atraso);
    }

    return $dados;
}

/* -----------------
 * ---------------------------------------
 * Relatório de Lista: Atividades Não Postadas
 * ---------------------------------------
 * -----------------
 */

/**
 * Geração de dados para Lista: Atividades Não Postadas
 *
 * @param $curso_ufsc
 * @param $curso_moodle
 * @param $modulos
 * @param $tutores
 * @return array
 */
function get_dados_estudante_sem_atividade_postada($curso_ufsc, $curso_moodle, $modulos, $tutores) {

    // Consulta
    $query_alunos_grupo_tutoria = query_estudante_sem_atividade_postada();

    return get_todo_list_data($curso_ufsc, $curso_moodle, $modulos, $tutores, $query_alunos_grupo_tutoria);
}

/* -----------------
 * ---------------------------------------
 * Lista: Atividades não Avaliadas
 * ---------------------------------------
 * -----------------
 */

/**
 * Geração de dados para Lista: Atividades não Avaliadas
 *
 * @param string $curso_ufsc
 * @param string $curso_moodle
 * @param array $modulos
 * @param array $tutores
 * @return array
 */
function get_dados_estudante_sem_atividade_avaliada($curso_ufsc, $curso_moodle, $modulos, $tutores) {

    // Consulta
    $query_alunos_grupo_tutoria = query_estudante_sem_atividade_avaliada();
    return get_todo_list_data($curso_ufsc, $curso_moodle, $modulos, $tutores, $query_alunos_grupo_tutoria);
}

/* -----------------
 * ---------------------------------------
 * Síntese: Avaliações em Atraso
 * ---------------------------------------
 * -----------------
 */

/**
 * Geração de dados dos tutores e seus respectivos alunos.
 *
 * @TODO ver se necessita colocar os foruns tambem e retirar o loop da query
 * @param array $modulos
 * @param string $curso_ufsc
 * @return array Array[tutores][aluno][unasus_data]
 */
function get_dados_atividades_nao_avaliadas($curso_ufsc, $curso_moodle, $modulos, $tutores) {

    $middleware = Middleware::singleton();

    // Consulta
    $query_alunos_grupo_tutoria = query_atividades_nao_avaliadas();

    // Recupera dados auxiliares
    $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc, $tutores);


    $group_tutoria = array();
    $lista_atividade = array();
    // Listagem da atividades por tutor
    $total_alunos = get_count_estudantes($curso_ufsc);
    $total_atividades = 0;

    foreach ($modulos as $atividades) {
        $total_atividades += count($atividades);
    }


    // Executa Consulta
    foreach ($grupos_tutoria as $grupo) {
        $group_array_do_grupo = new GroupArray();
        $array_das_atividades = array();

        foreach ($modulos as $modulo => $atividades) {
            foreach ($atividades as $atividade) {
                $params = array('courseid' => $modulo, 'assignmentid' => $atividade->assign_id, 'curso_ufsc' => $curso_ufsc,
                    'grupo_tutoria' => $grupo->id, 'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE);

                $result = $middleware->get_records_sql($query_alunos_grupo_tutoria, $params);

                // para cada assign um novo dado de avaliacao em atraso
                $array_das_atividades[$atividade->assign_id] = new dado_atividades_nota_atribuida($total_alunos[$grupo->id]);

                foreach ($result as $r) {

                    // Adiciona campos extras
                    $r->courseid = $modulo;
                    $r->assignid = $atividade->assign_id;
                    $r->submission_modified = (int) $r->submission_modified;

                    // Agrupa os dados por usuário
                    $group_array_do_grupo->add($r->user_id, $r);
                }
            }
        }
        $lista_atividade[$grupo->id] = $array_das_atividades;
        $group_tutoria[$grupo->id] = $group_array_do_grupo->get_assoc();
    }


    $timenow = time();
    $prazo_avaliacao = (get_prazo_avaliacao() * 60 * 60 * 24);


    $somatorio_total_atrasos = array();
    foreach ($group_tutoria as $grupo_id => $array_dados) {
        foreach ($array_dados as $results) {

            foreach ($results as $atividade) {
                if (!array_key_exists($grupo_id, $somatorio_total_atrasos)) {
                    $somatorio_total_atrasos[$grupo_id] = 0;
                }

                if ($atividade->status == 'draft' && $atividade->submission_modified + $prazo_avaliacao < $timenow) {

                    $lista_atividade[$grupo_id][$atividade->assignid]->incrementar_atraso();
                    $somatorio_total_atrasos[$grupo_id]++;
                }

            }
        }
    }


    $dados = array();
    foreach ($lista_atividade as $grupo_id => $grupo) {
        $data = array();
        $data[] = grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id);
        foreach ($grupo as $atividades) {
            $data[] = $atividades;
        }

        $data[] = new dado_media(($somatorio_total_atrasos[$grupo_id] * 100) / ($total_alunos[$grupo_id] * $total_atividades));
        $dados[] = $data;
    }

    return $dados;
}

/*
 * Cabeçalho para o sintese: avaliacoes em atraso
 */
function get_table_header_atividades_nao_avaliadas($modulos) {
    $header = get_table_header_modulos_atividades($modulos);
    $header[''] = array('Média');
    return $header;
}


/* -----------------
 * ---------------------------------------
 * Síntese: atividades concluídas
 * ---------------------------------------
 * -----------------
 */

function get_dados_atividades_nota_atribuida($curso_ufsc, $curso_moodle, $modulos, $tutores) {
    $middleware = Middleware::singleton();

    // Consulta
    $query_alunos_grupo_tutoria = query_atividades_nota_atribuida();

    // Recupera dados auxiliares
    $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc, $tutores);

    $group_tutoria = array();
    $lista_atividade = array();
    // Listagem da atividades por tutor
    $total_alunos = get_count_estudantes($curso_ufsc);
    $total_atividades = 0;

    foreach ($modulos as $atividades) {
        $total_atividades += count($atividades);
    }


    // Executa Consulta
    foreach ($grupos_tutoria as $grupo) {
        $group_array_do_grupo = new GroupArray();
        $group_array_das_atividades = new GroupArray();
        foreach ($modulos as $modulo => $atividades) {
            foreach ($atividades as $atividade) {
                $params = array('courseid' => $modulo, 'assignmentid' => $atividade->assign_id, 'curso_ufsc' => $curso_ufsc,
                    'grupo_tutoria' => $grupo->id, 'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE);
                $result = $middleware->get_records_sql($query_alunos_grupo_tutoria, $params);

                // para cada assign um novo dado de avaliacao em atraso
                $group_array_das_atividades->add($atividade->assign_id, new dado_atividades_nota_atribuida($total_alunos[$grupo->id]));

                foreach ($result as $r) {

                    // Adiciona campos extras
                    $r->courseid = $modulo;
                    $r->assignid = $atividade->assign_id;
                    $r->duedate = $atividade->duedate;

                    // Agrupa os dados por usuário
                    $group_array_do_grupo->add($r->user_id, $r);
                }
            }
        }
        $lista_atividade[$grupo->id] = $group_array_das_atividades->get_assoc();
        $group_tutoria[$grupo->id] = $group_array_do_grupo->get_assoc();
    }

    $somatorio_total_atrasos = array();
    foreach ($group_tutoria as $grupo_id => $array_dados) {
        foreach ($array_dados as $id_aluno => $aluno) {
            foreach ($aluno as $atividade) {
                $lista_atividade[$grupo_id][$atividade->assignid][0]->incrementar_atraso();
                if (!key_exists($grupo_id, $somatorio_total_atrasos)) {
                    $somatorio_total_atrasos[$grupo_id] = 0;
                }
                $somatorio_total_atrasos[$grupo_id]++;
            }
        }
    }

    $dados = array();
    foreach ($lista_atividade as $grupo_id => $grupo) {
        $data = array();
        $data[] = grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id);
        foreach ($grupo as $atividades) {
            $data[] = $atividades[0];
        }
        $data[] = new dado_media(($somatorio_total_atrasos[$grupo_id] * 100) / ($total_alunos[$grupo_id] * $total_atividades));
        $dados[] = $data;
    }

    return $dados;
}

/*
 * Cabeçalho para o sintese: atividades concluidas
 */
function get_table_header_atividades_nota_atribuida($modulos) {
    return get_table_header_atividades_nao_avaliadas($modulos);
}

/* -----------------
 * ---------------------------------------
 * Uso do Sistema pelo Tutor (horas)
 * ---------------------------------------
 * -----------------
 */

/**
 * @TODO arrumar media
 */
function get_dados_uso_sistema_tutor($curso_ufsc, $curso_moodle, $tutores) {
    $lista_tutores = get_tutores_menu($curso_ufsc);
    $dados = array();
    $tutores = array();
    foreach ($lista_tutores as $tutor_id => $tutor) {
        $media = new dado_media(rand(0, 20));

        $tutores[] = array(new tutor($tutor, $tutor_id, $curso_moodle),
            new dado_uso_sistema_tutor(rand(5, 20)),
            new dado_uso_sistema_tutor(rand(5, 20)),
            new dado_uso_sistema_tutor(rand(5, 20)),
            new dado_uso_sistema_tutor(rand(5, 20)),
            new dado_uso_sistema_tutor(rand(5, 20)),
            new dado_uso_sistema_tutor(rand(5, 20)),
            $media->value(),
            new dado_somatorio(rand(10, 20) + rand(10, 20) + rand(10, 20) + rand(10, 20) + rand(10, 20) + rand(10, 20)));
    }
    $dados["Tutores"] = $tutores;

    return $dados;
}

/**
 * @TODO arrumar media
 */
function get_table_header_uso_sistema_tutor() {
    return array('Tutor', 'Jun/Q4', 'Jul/Q1', 'Jul/Q2', 'Jul/Q3', 'Jul/Q4', 'Ago/Q1', 'Media', 'Total');
}

/**
 * @TODO arrumar media
 */
function get_dados_grafico_uso_sistema_tutor($modulo, $tutores, $curso_ufsc) {
    $tutores = get_tutores_menu($curso_ufsc);

    $dados = array();
    foreach ($tutores as $tutor) {
        $dados[$tutor] = array('Jun/Q4' => rand(5, 20), 'Jul/Q1' => rand(5, 20), 'Jul/Q2' => rand(5, 20), 'Jul/Q3' => rand(5, 20), 'Jul/Q4' => rand(5, 20), 'Ago/Q1' => rand(5, 20));
    }

    return $dados;
}

/* -----------------
 * ---------------------------------------
 * Uso do Sistema pelo Tutor (acesso)
 * ---------------------------------------
 * -----------------
 */

function get_dados_acesso_tutor($curso_ufsc, $curso_moodle, $tutores) {
    $middleware = Middleware::singleton();

    // Consulta
    $query = query_acesso_tutor();

    $params = array('tipo_tutor' => GRUPO_TUTORIA_TIPO_TUTOR);
    $result = $middleware->get_recordset_sql($query, $params);

    //Para cada linha da query ele cria um ['pessoa']=>['data_entrada1','data_entrada2]
    $group_array = new GroupArray();
    foreach ($result as $r) {
        $dia = $r['calendar_day'];
        $mes = $r['calendar_month'];
        if ($dia < 10)
            $dia = '0' . $dia;
        if ($mes < 10)
            $mes = '0' . $mes;
        $group_array->add($r['userid'], $dia . '/' . $mes);
    }
    $dados = $group_array->get_assoc();

    // Intervalo de dias no formato d/m
    $end = new DateTime();
    $interval = new DateInterval('P60D');

    $begin = clone $end;
    $begin->sub($interval);

    $increment = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $increment, $end);

    $dias_meses = array();
    foreach ($daterange as $date) {
        $dias_meses[] = $date->format('d/m');
    }


    //para cada resultado da busca ele verifica se esse dado bate no "calendario" criado com o
    //date interval acima
    $result = new GroupArray();
    foreach ($dados as $id => $datas) {
        foreach($dias_meses as $dia){
            (in_array($dia, $datas)) ?
                $result->add($id, new dado_acesso_tutor(true)) :
                $result->add($id, new dado_acesso_tutor(false));
        }

    }
    $result = $result->get_assoc();

    $nomes_tutores = grupos_tutoria::get_tutores_curso_ufsc($curso_ufsc);

    //para cada resultado que estava no formato [id]=>[dados_acesso]
    // ele transforma para [tutor,dado_acesso1,dado_acesso2]
    $retorno = array();
    foreach($result as $id => $values){
        $dados = array();
        $nome = (array_key_exists($id, $nomes_tutores)) ? $nomes_tutores[$id] : $id;
        array_push($dados, new tutor($nome,$id,$curso_moodle));
        foreach($values as $value){
            array_push($dados, $value);
        }
        $retorno[] = $dados;
    }


    return array('Tutores'=>$retorno);
}

/*
 * Cabeçalho para o relatorio de uso do sistema do tutor, cria um intervalo de tempo de 60 dias atras
 */
function get_table_header_acesso_tutor() {
    $end = new DateTime();
    $interval = new DateInterval('P60D');

    $begin = clone $end;
    $begin->sub($interval);

    $increment = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $increment, $end);

    $meses = array();
    foreach ($daterange as $date) {
        $mes = strftime("%B", $date->format('U'));
        if (!array_key_exists($mes, $meses)) {
            $meses[$mes] = null;
        }
        $meses[$mes][] = $date->format('d/m');
    }

    return $meses;
}

/* -----------------
 * ---------------------------------------
 * Potenciais Evasões
 * ---------------------------------------
 * -----------------
 */

function get_dados_potenciais_evasoes($curso_ufsc, $curso_moodle, $modulos, $tutores) {
    global $CFG;
    $middleware = Middleware::singleton();

    // Consulta
    $query = query_potenciais_evasoes();


    // Recupera dados auxiliares
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);
    $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc, $tutores);

    $grupos = array();


    // Executa Consulta
    foreach ($grupos_tutoria as $grupo) {
        $group_array_do_grupo = new GroupArray();
        foreach ($modulos as $modulo => $atividades) {

            foreach ($atividades as $atividade) {
                $params = array('courseid' => $modulo, 'assignmentid' => $atividade->assign_id,
                    'curso_ufsc' => $curso_ufsc, 'grupo_tutoria' => $grupo->id, 'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE);
                $result = $middleware->get_records_sql($query, $params);

                foreach ($result as $r) {

                    // Adiciona campos extras
                    $r->courseid = $modulo;
                    $r->assignid = $atividade->assign_id;
                    $r->duedate = $atividade->duedate;

                    // Agrupa os dados por usuário
                    $group_array_do_grupo->add($r->user_id, $r);
                }
            }
        }
        $grupos[$grupo->id] = $group_array_do_grupo->get_assoc();
    }




    //pega a hora atual para comparar se uma atividade esta atrasada ou nao
    $timenow = time();
    $dados = array();



    foreach ($grupos as $grupo_id => $array_dados) {
        $estudantes = array();
        foreach ($array_dados as $id_aluno => $aluno) {
            $dados_modulos = array();
            $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $curso_moodle);
            foreach ($aluno as $atividade) {

                //para cada novo modulo ele cria uma entrada de dado_potenciais_evasoes com o maximo de atividades daquele modulo
                if (!array_key_exists($atividade->courseid, $dados_modulos)) {
                    $dados_modulos[$atividade->courseid] = new dado_potenciais_evasoes(sizeof($modulos[$atividade->courseid]));
                }

                //para cada atividade nao feita ele adiciona uma nova atividade nao realizada naquele modulo
                if (is_null($atividade->submission_date) && $atividade->duedate <= $timenow) {
                    $dados_modulos[$atividade->courseid]->add_atividade_nao_realizada();
                }
            }

            $atividades_nao_realizadas_do_estudante = 0;
            foreach ($dados_modulos as $key => $modulo) {
                $lista_atividades[] = $modulo;
                $atividades_nao_realizadas_do_estudante += $modulo->get_total_atividades_nao_realizadas();
            }

            if ($atividades_nao_realizadas_do_estudante > $CFG->report_unasus_tolerancia_potencial_evasao) {
                $estudantes[] = $lista_atividades;
            }
            $lista_atividades = null;
        }

        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = $estudantes;
    }
    return $dados;
}

function get_table_header_potenciais_evasoes($modulos) {
    $nome_modulos = get_id_nome_modulos();
    if (is_null($modulos)) {
        $modulos = get_id_modulos();
    }

    $header = array();
    $header[] = 'Estudantes';
    foreach ($modulos as $modulo) {
        $header[] = new dado_modulo($modulo, $nome_modulos[$modulo]);
    }
    return $header;
}

/* /\/\/\/\/\/\/\/\/\/\/\/\/\/\/\
 * ISOLADOS
 * /\/\/\/\/\/\/\/\/\/\/\/\/\/\/\
 */

function get_table_header_modulos_atividades($modulos = array())
{
    $atividades_modulos = query_atividades_modulos($modulos);

    $group = new GroupArray();
    $modulos = array();
    $header = array();

    // Agrupa atividades por curso e cria um índice de cursos
    foreach ($atividades_modulos as $atividade) {
        $modulos[$atividade->course_id] = $atividade->course_name;
        $group->add($atividade->course_id, $atividade);
    }

    $group_assoc = $group->get_assoc();

    foreach ($group_assoc as $course_id => $atividades) {
        $course_url = new moodle_url('/course/view.php', array('id' => $course_id));
        $course_link = html_writer::link($course_url, $modulos[$course_id]);
        $dados = array();

        foreach ($atividades as $atividade) {
            if (array_key_exists('assign_id', $atividade)) {
                $cm = get_coursemodule_from_instance('assign', $atividade->assign_id, $course_id, null, MUST_EXIST);

                $atividade_url = new moodle_url('/mod/assign/view.php', array('id' => $cm->id));
                $dados[] = html_writer::link($atividade_url, $atividade->assign_name);
            } else {
                $forum_url = new moodle_url('/mod/forum/view.php', array('id' => $atividade->idnumber));
                $dados[] = html_writer::link($forum_url, $atividade->itemname);
            }
        }
        $header[$course_link] = $dados;
    }
    return $header;
}

function get_header_estudante_sem_atividade_postada($size) {
    $content = array();
    for ($index = 0; $index < $size - 1; $index++) {
        $content[] = '';
    }
    $header['Atividades não resolvidas'] = $content;
    return $header;
}

function get_todo_list_data($curso_ufsc, $curso_moodle, $modulos, $tutores, $query) {
    $middleware = Middleware::singleton();

    // Recupera dados auxiliares
    $nomes_estudantes = grupos_tutoria::get_estudantes_curso_ufsc($curso_ufsc);
    $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc, $tutores);

    $grupos = array();

    // Executa Consulta
    foreach ($grupos_tutoria as $grupo) {
        $group_array_do_grupo = new GroupArray();
        foreach ($modulos as $modulo => $atividades) {
            foreach ($atividades as $atividade) {
                $params = array('courseid' => $modulo, 'assignmentid' => $atividade->assign_id,
                    'curso_ufsc' => $curso_ufsc, 'grupo_tutoria' => $grupo->id, 'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE);
                $result = $middleware->get_records_sql($query, $params);

                foreach ($result as $r) {

                    // Adiciona campos extras
                    $r->courseid = $modulo;
                    $r->assignid = $atividade->assign_id;
                    $r->duedate = $atividade->duedate;

                    // Agrupa os dados por usuário
                    $group_array_do_grupo->add($r->user_id, $r);
                }
            }
        }
        $grupos[$grupo->id] = $group_array_do_grupo->get_assoc();
    }



    $id_nome_modulos = get_id_nome_modulos();
    $id_nome_atividades = get_id_nome_atividades();


    $dados = array();
    foreach ($grupos as $grupo_id => $array_dados) {
        $estudantes = array();
        foreach ($array_dados as $id_aluno => $aluno) {
            $lista_atividades[] = new estudante($nomes_estudantes[$id_aluno], $id_aluno, $curso_moodle);

            $atividades_modulos = new GroupArray();

            foreach ($aluno as $atividade) {
                $atividades_modulos->add($atividade->courseid, $atividade->assignid);
            }


            $ativ_mod = $atividades_modulos->get_assoc();
            foreach ($ativ_mod as $key => $modulo) {
                $lista_atividades[] = new dado_modulo($key, $id_nome_modulos[$key]);
                foreach ($modulo as $atividade) {
                    $lista_atividades[] = new dado_atividade($atividade, $id_nome_atividades[$atividade], $key);
                }
            }


            $estudantes[] = $lista_atividades;
            $lista_atividades = null;
        }
        $dados[grupos_tutoria::grupo_tutoria_to_string($curso_ufsc, $grupo_id)] = $estudantes;
    }
    return $dados;
}


