<?php

/**
 * Class Factory
 *
 * Esta classe tem como objetivo ser uma central de informações para os filtros e gráficos
 * durante o processo de geração deste plugin.
 *
 * Ela é uma classe singleton para que em qualquer escopo deste plugin as variáveis setadas sejam as mesmas.
 *
 * Esta opção se mostrou altamente eficiente já que a quantidade de parametros passados a cada função
 * estavam crescendo de acordo com a complexidade dos gráficos e a utilização destas variáveis só são
 * invocadas quando realmente necessárias.
 *
 * Os atributos setados no construtor da classe são os valores padrão de filtragem e parametros pegos via
 * GET e POST, alguns parametros são protected para evitar sua alteração desnecessária.
 *
 * Os atributos da barra de filtragem, que variam de relatório em relatório são setados no arquivo
 * index.php de acordo com o relatório selecionado.
 */
define('AGRUPAR_TUTORES', 'TUTORES');
define('AGRUPAR_POLOS', 'POLOS');
define('AGRUPAR_COHORTS', 'COHORTS');
define('AGRUPAR_ORIENTADORES', 'ORIENTADORES');

class Factory {

    // Atributos globais

    /** @var bool|string $curso_ufsc Código de curso UFSC associdado a este relatório */
    protected $curso_ufsc;

    /** @var int|mixed $curso_moodle Código do curso Moodle em que este relatório foi acessado */
    protected $curso_moodle;

    /** @var array $cursos_ativos Cursos UFSC que estão ativos (Middleware) */
    protected $cursos_ativos;

    /** @var  string $relatorio relatório atual que será mostrado */
    protected $relatorio;

    /** @var  mixed $modo_exibicao valores possíveis: null, tabela, grafico_valores, grafico_porcentagens, grafico_pontos */
    protected $modo_exibicao;

    // Atributos para construir tela de filtros
    public $mostrar_barra_filtragem; //mostrar ou esconder filtro
    public $mostrar_botoes_grafico;
    public $mostrar_botoes_dot_chart;
    public $mostrar_filtro_polos;
    public $mostrar_filtro_modulos;
    public $mostrar_filtro_tutores;
    public $mostrar_filtro_intervalo_tempo;
    public $mostrar_aviso_intervalo_tempo;

    public $mostrar_filtro_cohorts;
    public $mostrar_botao_exportar_csv;

    // Armazenamento de valores definidos nos filtros
    public $cohorts_selecionados;
    public $modulos_selecionados;
    public $polos_selecionados;
    public $tutores_selecionados;
    public $orientadores_selecionados;
    public $agrupar_relatorios;

    // Atributos para os gráficos e tabelas
    public $texto_cabecalho;

    //Atributos especificos para os relatorios de uso sistema tutor e acesso tutor
    public $data_inicio;
    public $data_fim;

    // Singleton
    private static $report;

    protected function __construct() {
        //Atributos globais
        $this->curso_ufsc = get_curso_ufsc_id();
        $this->curso_moodle = get_course_id();
        $this->cursos_ativos = get_cursos_ativos_list();

        //Atributos para os gráficos
        //Por default os módulos selecionados são os módulos que o curso escolhido possui
        $this->texto_cabecalho = 'Estudantes';

        $modulos_raw = optional_param_array('modulos', null, PARAM_INT);
        if (is_null($modulos_raw)) {
            $modulos_raw = array_keys(get_id_nome_modulos(get_curso_ufsc_id()));
        }
        $this->cohorts_selecionados = optional_param_array('cohorts', null, PARAM_INT);
        $this->modulos_selecionados = get_atividades_cursos(get_modulos_validos($modulos_raw));
        $this->polos_selecionados = optional_param_array('polos', null, PARAM_INT);
        $this->tutores_selecionados = optional_param_array('tutores', null, PARAM_INT);
        $this->agrupamentos_membros = get_agrupamentos_membros(get_modulos_validos($modulos_raw));
        $this->orientadores_selecionados = optional_param_array('orientadores', null, PARAM_INT);

        //AGRUPAMENTO DO RELATORIO
        $agrupar_relatorio = optional_param('agrupar_tutor_polo_select', null, PARAM_INT);
        switch ($agrupar_relatorio) {
            case 1:
                $this->agrupar_relatorios = AGRUPAR_POLOS;
                break;
            case 2:
                $this->agrupar_relatorios = AGRUPAR_COHORTS;
                break;
            case 3:
                $this->agrupar_relatorios = AGRUPAR_ORIENTADORES;
            default:
                $this->agrupar_relatorios = AGRUPAR_TUTORES;
                break;
        }

        //Atributos especificos para os relatorios de uso sistema tutor e acesso tutor
        $data_inicio = optional_param('data_inicio', null, PARAM_TEXT);
        $data_fim = optional_param('data_fim', null, PARAM_TEXT);

        if (date_interval_is_valid($data_inicio, $data_fim)) {
            $this->data_inicio = $data_inicio;
            $this->data_fim = $data_fim;
        }
        
        // Verifica se é um relatorio valido
        $this->set_relatorio(optional_param('relatorio', null, PARAM_ALPHANUMEXT));
        
        // Verifica se é um modo de exibicao valido
        $this->set_modo_exibicao(optional_param('modo_exibicao', null, PARAM_ALPHANUMEXT));
    }

    /**
     * Fabrica um objeto com as definições dos relatórios que também é um singleton
     * 
     * @global type $CFG
     * @return Factory
     * @throws Exception
     */

    public static function singleton() {

        global $CFG;

        $report = optional_param('relatorio', null, PARAM_ALPHANUMEXT);

        if (! in_array($report, report_unasus_relatorios_validos_list())){
            print_error('unknow_report', 'report_unasus');
            return false;
        }

        $class_name = "report_{$report}";

        // carrega arquivo de definição do relatório
        require_once $CFG->dirroot . "/report/unasus/reports/{$class_name}.php";

        if (!class_exists($class_name)) {
            throw new Exception('Missing format class.');
        }

        if (!isset(self::$report)) {
            self::$report = new $class_name;
            self::$report->initialize();
        }

        return self::$report;
    }

    /**
     * Verifica se é um relatório válido e o seta
     * @deprecated 
     * @param string $relatorio nome do relatorio
     */
    public function set_relatorio($relatorio) {
        $options = report_unasus_relatorios_validos_list();
        if (in_array($relatorio, $options)) {
            $this->relatorio = $relatorio;
        } else {
            print_error('unknow_report', 'report_unasus');
        }
    }

    // Previne que o usuário clone a instância
    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }


    /**
     * @return int curso ufsc
     */
    public function get_curso_ufsc() {
        return $this->curso_ufsc;
    }

    /**
     * @return int curso ufsc
     */
    public function get_curso_moodle() {
        return $this->curso_moodle;
    }

    /**
     * @return string nome de uma classe
     */
    public function get_estrutura_dados_relatorio() {
        return "dado_{$this->relatorio}";
    }

    /**
     * Verifica se o relatório possui gráfico definido
     *
     * @return bool
     */
    public function relatorio_possui_grafico($report) {
        $method = 'get_dados_grafico';

        if (method_exists($report, $method))
            return true;
        return false;
    }

    /**
     * @return string nome do relatorio
     */
    public function get_relatorio() {
        return $this->relatorio;
    }

    /**
     * Verifica se é um modo de exibição válido e o seta
     *
     * @param string $modo_exibicao tipo de relatorio a ser exibido
     */
    public function set_modo_exibicao($modo_exibicao) {
        $options = array(null, 'grafico_valores', 'tabela', 'grafico_porcentagens', 'grafico_pontos', 'export_csv');
        if (in_array($modo_exibicao, $options)) {
            $this->modo_exibicao = $modo_exibicao;
        } else {
            print_error('unknow_report', 'report_unasus');
        }
    }

    /**
     * @return string tipo de relatorio a ser exibido
     */
    public function get_modo_exibicao() {
        return $this->modo_exibicao;
    }


    /**
     * Returns course context instance.
     *
     * @return context_course
     */
    public function get_context() {
        return context_course::instance($this->get_curso_moodle());
    }

    /**
     * @return array Parametros para o GET da pagina HTML
     */
    public function get_page_params() {
        return array('relatorio' => $this->get_relatorio(), 'course' => $this->get_curso_moodle());
    }

    /**
     * @return array array com as ids dos modulos
     */
    public function get_modulos_ids() {
        return array_keys($this->modulos_selecionados);
    }

    /**
     * @return bool se as datas foram setadas no construtor, passando pelo date_interval_is_valid elas são validas
     */
    public function datas_validas() {
        return (!is_null($this->data_inicio) && !is_null($this->data_fim));
    }

    /**
     * Retorna TRUE se usuário faz parte de um determinado agrupamento p/ um determinado course_id
     * @param $grouping_id
     * @param $course_id
     * @param $user_id
     * @return bool
     */
    public function is_member_of($grouping_id, $course_id, $user_id) {
        return isset($this->agrupamentos_membros[$grouping_id][$course_id][$user_id]);
    }

    static function eliminate_html ($data){
        return strip_tags($data);
    }

    function get_table_header_modulos_atividades($mostrar_nota_final = false, $mostrar_total = false) {
        /** @var $factory Factory */
        $factory = Factory::singleton();

        $atividades_cursos = get_atividades_cursos($factory->get_modulos_ids(), $mostrar_nota_final, $mostrar_total);
        $header = array();

        foreach ($atividades_cursos as $course_id => $atividades) {
            $course_url = new moodle_url('/course/view.php', array('id' => $course_id));
            $course_link = html_writer::link($course_url, $atividades[0]->course_name);

            $header[$course_link] = $atividades;
        }
        return $header;
    }

    function get_table_header_tcc_portfolio_entrega_atividades($is_tcc = false) {

        $group_array = new GroupArray();
        process_header_atividades_lti($this->get_modulos_ids(), $group_array, $is_tcc);

        $atividades_cursos = $group_array->get_assoc();
        $header = array();

        foreach ($atividades_cursos as $course_id => $atividades) {
            if (!empty($atividades)) {
                $course_url = new moodle_url('/course/view.php', array('id' => $course_id));
                $course_link = html_writer::link($course_url, $atividades[0]->course_name);

                $header[$course_link] = $atividades;
            }
        }

        return $header;
    }

}