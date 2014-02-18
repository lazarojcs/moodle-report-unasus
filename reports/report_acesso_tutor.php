<?php

class report_acesso_tutor extends Factory {

    public function __construct() {
    }

    //AJUSTAR PARA USAR HERANÇA, não parâmetro
    public function initialize($factory, $filtro = true, $aviso = false) {
        $factory->mostrar_barra_filtragem = $filtro;
        $factory->mostrar_botoes_grafico = false;
        $factory->mostrar_botoes_dot_chart = false;
        $factory->mostrar_filtro_polos = false;
        $factory->mostrar_filtro_cohorts = false;
        $factory->mostrar_filtro_modulos = false;
        $factory->mostrar_filtro_intervalo_tempo = true;
        $factory->mostrar_aviso_intervalo_tempo = $aviso;
    }

    public function render_report_default($renderer){
        echo $renderer->build_page();
    }

    public function render_report_table($renderer, $object, $factory) {
        if ($factory->datas_validas()) {
            $factory->texto_cabecalho = 'Tutores';
            $this->initialize($factory, false);
            echo $renderer->build_report($object);
        }
        $this->initialize($factory, false, true);
        echo $renderer->build_page();
    }

    public function get_dados() {
        /** @var $factory Factory */
        $factory = Factory::singleton();

        $middleware = Middleware::singleton();

        // Consulta
        $query = query_acesso_tutor($factory->tutores_selecionados);

        $params = array('tipo_tutor' => GRUPO_TUTORIA_TIPO_TUTOR, 'curso_ufsc' => get_curso_ufsc_id());
        $result = $middleware->get_recordset_sql($query, $params);

        //Para cada linha da query ele cria um ['pessoa']=>['data_entrada1','data_entrada2]
        $group_array = new GroupArray();
        foreach ($result as $r) {
            $dia = $r['calendar_day'];
            $mes = $r['calendar_month'];
            $ano = $r['calendar_year'];
            if ($dia < 10)
                $dia = '0' . $dia;
            if ($mes < 10)
                $mes = '0' . $mes;
            $group_array->add($r['userid'], $dia . '/' . $mes . '/' . $ano);
        }
        $dados = $group_array->get_assoc();


        //Converte a string data pra um DateTime
        $data_inicio = date_create_from_format('d/m/Y', $factory->data_inicio);
        $data_fim = date_create_from_format('d/m/Y', $factory->data_fim);

        // Intervalo de dias no formato d/m/Y
        $dias_meses = get_time_interval($data_inicio, $data_fim, 'P1D', 'd/m/Y');


        //para cada resultado da busca ele verifica se esse dado bate no "calendario" criado com o
        //date interval acima
        $result = new GroupArray();
        foreach ($dados as $id => $datas) {
            foreach ($dias_meses as $dia) {
                (in_array($dia, $datas)) ?
                        $result->add($id, new dado_acesso_tutor(true)) :
                        $result->add($id, new dado_acesso_tutor(false));
            }
        }
        $result = $result->get_assoc();

        $nomes_tutores = grupos_tutoria::get_tutores_curso_ufsc($factory->get_curso_ufsc());

        //para cada resultado que estava no formato [id]=>[dados_acesso]
        // ele transforma para [tutor,dado_acesso1,dado_acesso2]
        $retorno = array();
        foreach ($result as $id => $values) {
            $dados = array();
            $nome = (array_key_exists($id, $nomes_tutores)) ? $nomes_tutores[$id] : $id;
            array_push($dados, new pessoa($nome, $id, $factory->get_curso_moodle()));
            foreach ($values as $value) {
                array_push($dados, $value);
            }
            $retorno[] = $dados;
        }

        return array('Tutores' => $retorno);
    }

    public function get_table_header() {
        /** @var $factory Factory */
        $factory = Factory::singleton();

        return get_time_interval_com_meses($factory->data_inicio, $factory->data_fim, 'P1D', 'd/m/Y');
    }

}