M.report_unasus = {};

M.report_unasus.init = function(Y) {

    // Se javascript for executado ele mostra o botão ocultar/mostrar filtros
    var $filter_button = Y.one('#button-mostrar-filtro');
    var $filter_div = Y.one('#div_filtro');

    if ($filter_button.hasClass('visible')) {
        $filter_button.removeClass('hidden');
        $filter_div.addClass('hidden');
    } else {
        $filter_div.addClass('visible');
    }

    // Ao clicar no botao mostrar/ocultar filtros ele esconde/mostra a barra e troca o seu texto
    Y.delegate('click', function(e) {
        var $filter_button = Y.one('#button-mostrar-filtro');
        var $filter_div = Y.one('#div_filtro');

        if ($filter_div.hasClass('visible')) {
            $filter_button.set('text', 'Mostrar Filtro');
            $filter_div.addClass('hidden');
            $filter_div.removeClass('visible');
        } else {
            $filter_button.set('text', 'Ocultar Filtro');
            $filter_div.addClass('visible');
            $filter_div.removeClass('hidden');
        }
    }, document, '#button-mostrar-filtro');

    //Botoes de selecionar todos e limpar seleção
    Y.delegate('click', function(e) {
        select_all('#multiple_cohort', true);
    }, document, '#select_all_cohort');
    
    Y.delegate('click', function(e) {
        select_all('#multiple_cohort', false);
    }, document, '#select_none_cohort');
    
    Y.delegate('click', function(e) {
        select_all('#multiple_modulo', true);
    }, document, '#select_all_modulo');

    Y.delegate('click', function(e) {
        select_all('#multiple_modulo', false);
    }, document, '#select_none_modulo');

    Y.delegate('click', function(e) {
        select_all('#multiple_polo', true);
    }, document, '#select_all_polo');

    Y.delegate('click', function(e) {
        select_all('#multiple_polo', false);
    }, document, '#select_none_polo');

    Y.delegate('click', function(e) {
        select_all('#multiple_tutor', true);
    }, document, '#select_all_tutor');

    Y.delegate('click', function(e) {
        select_all('#multiple_tutor', false);
    }, document, '#select_none_tutor');

};

/**
 * Função que seleciona/desceleciona todos os itens
 * @param target id do select box
 * @param select boolean -- true para selecionar todos, false para descelecionar
 *
 */
function select_all(target, select) {
    var multiple = Y.one(target);

    multiple.get('options').each(function() {
        if (select) {
            this.set('selected', true);
        } else {
            this.set('selected', false);
        }
    });
    multiple.focus();
}

//M.report_unasus.init_date_picker = YUI().use('calendar', function (Y) {
//    var hoje = new Date();
//    var mes_passado = new Date();
//    mes_passado.setMonth( mes_passado.getMonth() - 1 );
//
//    // Create a new instance of Calendar, setting its width
//    // and height, allowing the dates from the previous
//    // and next month to be visible and setting the initial
//    // date to be November, 1982.
//    var calendar = new Y.Calendar({
//        contentBox: "#calendario_inicio",
//        height:'225px',
//        width:'100%',
//        showPrevMonth: true,
//        showNextMonth: true,
//        date: mes_passado,
//        maximumDate: hoje}).render();
//
//    var calendarfim = new Y.Calendar({
//        contentBox: "#calendario_fim",
//        height:'225px',
//        width:'100%',
//        showPrevMonth: true,
//        showNextMonth: true,
//        maximumDate: hoje}).render();
//
//});

var chart1;
/**
 * Gráfico de Stacked Bars
 *
 * @param Y objeto da YAHOO javascript
 * @param dados_grafico array -- dados que alimentarão o gráfico array([item]=>(int1, int2, int3))
 * @param tipos array -- tipos de dados para legenda/cores do gráfico
 * @param title String -- titulo do gráfico
 * @param porcentagem boolean -- se o gráfico é do tipo porcentagem ou não
 */
M.report_unasus.init_graph = function(Y, dados_grafico, tipos, title, porcentagem) {
    var size = Object.keys(dados_grafico).length;
    var stack_option = 'normal';
    if (porcentagem)
        stack_option = 'percent';

    var options = {
        chart: {
            // ID da div para colocar o gráfico
            renderTo: 'container',
            type: 'bar',
            height: 200 + (60 * size)
        },
        title: {
            text: title
        },
        xAxis: {
            categories: []
        },
        yAxis: {
            title: {
                text: "Estado das avalições"
            }
        },
        legend: {
            reversed: true,
            layout: 'vertical'
        },
        plotOptions: {
            series: {

                stacking: stack_option

            }
        },
        series: []
    };

    // se for um gráfico de porcentagem cria uma legenda específica
    if (porcentagem) {
        options['tooltip'] = {
            formatter: function() {
                return '<b>' + this.x + '</b>' + '<br><span style="color:' + this.series.color + '">' + this.series.name + '</span>: ' + Math.round(this.percentage * 100) / 100 + ' % (quantidade: ' + this.y + ' de ' + this.total + ')';
            }
        }
    }

    // Cria um item no array data para cada tipo de informação
    var data = [];
    for (tipo in tipos) {
        data[tipo] = [];
    }

    for (tutor in dados_grafico) {
        //Plota uma nova linha no gráfico para cada tutor
        options.xAxis.categories.push(tutor);

        //Para cada informacao de um tutor ele adiciona um novo item no array data
        //data = [tipo1] => [dado_tipo1_tutor1, dado_tipo1_tutor2, dado_tipo1_tutor3],
        //       [tipo2] => [dado_tipo2_tutor1, dado_tipo2_tutor2, dado_tipo2_tutor3]
        for (d in data) {
            data[d].push(dados_grafico[tutor][d]);
        }

    }

    for (tipo in tipos) {
        options.series.push({
            name: tipos[tipo],
            data: data[tipo]
        })
    }

    chart1 = new Highcharts.Chart(options)
}

/**
 * @param Y objeto da Yahoo javascript
 * @param dados dados para popular o gráfico, no formato
 *        array ('tutor 1'=> ('sem1'=> 5,'sem2'=> 12, 'sem3'=> 5 )
 *               'tutor 2'=> ('sem1'=> 12,'sem2'=>8, 'sem3'=>20 ))
 *
 */
M.report_unasus.init_dot_graph = function(Y, dados) {
    // Contadores de dados
    // xs = vai de 0 a numero de semanas, repetindo isto para cada tutor
    // EX: 3 tutores, 4 semanas xs = [0,1,2,3,0,1,2,3,0,1,2,3]
    var xs = [];
    // ys = vai de numero de semanas até 1, repete cada numero até acabarem todos os tutores daquela data
    // EX: 3 tutores, 4 semanas ys = [3,3,3,3,2,2,2,2,1,1,1,1]
    var ys = [];

    // Dados de cada tutor por semana
    // EX: 3 tutores, 4 semanas xy = [12 ,5 ,7 ,3 ,15 ,8 ,10 ,2 ,10 ,10 ,20 ,12]
    //                                Tutor 1     | Tutor 2     | Tutor 3
    //                                s1,s2,s3,s4 | s1,s2,s3,s4 | s1,s2,s3,s4
    var data = [];

    //Legendas axisX = Semanas, axisY = Nome dos Tutores
    var axisy = [];
    var axisx = [];


    var count_tutor = objectLength(dados);
    var ysize = count_tutor - 1;
    var count_semana;
    // A legenda do eixo X é pega com os dados correspondentes do promeiro tutor iterado
    var primeira_vez = true;

    for (tutor in dados) {
        count_semana = 0;
        axisy.push(tutor);

        for (dias in dados[tutor]) {
            if (primeira_vez) {
                axisx.push(dias);
            }
            data.push(dados[tutor][dias]);
            ys.push(count_tutor);
            xs.push(count_semana);
            count_semana++;
        }
        count_tutor--;
        primeira_vez = false;
    }
    axisy = axisy.reverse();

    var r = Raphael("container"), xs, ys, data, axisy , axisx;
    r.dotchart(10, 10, 620, 260, xs, ys, data, {
        //desenho no gráfico, uma bola - só existe essa opcao no GRafael
        symbol: "o",
        //Tamanho máximo da bola
        max: 15,
        //colorida
        heat: true,
        //aonde serao renderizados os eixos, cima, direita, baixo, esquerda
        axis: "0 0 1 1",
        axisxstep: count_semana - 1,
        axisystep: ysize,
        axisxlabels: axisx,
        //Modo como serão renderizados a ligação entre legenda - linha divisória, neste caso um "+"
        axisxtype: "+",
        axisytype: "+",
        axisylabels: axisy
    }).hover(function() {
            this.marker = this.marker || r.tag(this.x, this.y, this.value, 0, this.r + 2).insertBefore(this);
            this.marker.show();
        }, function() {
            this.marker && this.marker.hide();
        });
};

/**
 * @param obj array(array())
 * @return int quantidade de itens pai no array, utilizado para saber quantos tutores foram enviados
 **/
function objectLength(obj) {
    var result = 0;
    for (var prop in obj) {
        if (obj.hasOwnProperty(prop)) {
            result++;
        }
    }
    return result;
}

M.report_unasus.fixed_columns = function(Y) {
    // Grab all cells in the user names column.
    var userColumn = Y.all(SELECTORS.USERCELL),

    // Create a floating table.
        floatingUserColumn = Y.Node.create('<div aria-hidden="true" role="presentation" class="floater sideonly"></div>'),

    // Get the XY for the floating element.
        coordinates = this._getRelativeXY(this.firstUserCell);

    // Generate the new fields.
    userColumn.each(function(node) {
        var height = node.getComputedStyle(HEIGHT);
        // Nasty hack to account for Internet Explorer
        if(Y.UA.ie !== 0) {
            var allHeight = node.get('offsetHeight');
            var marginHeight = parseInt(node.getComputedStyle('marginTop'),10) +
                parseInt(node.getComputedStyle('marginBottom'),10);
            var paddingHeight = parseInt(node.getComputedStyle('paddingTop'),10) +
                parseInt(node.getComputedStyle('paddingBottom'),10);
            var borderHeight = parseInt(node.getComputedStyle('borderTopWidth'),10) +
                parseInt(node.getComputedStyle('borderBottomWidth'),10);
            height = allHeight - marginHeight - paddingHeight - borderHeight;
        }
        // Create and configure the new container.
        var containerNode = Y.Node.create('<div></div>');
        containerNode.set('innerHTML', node.get('innerHTML'))
            .setAttribute('class', node.getAttribute('class'))
            .setAttribute('data-uid', node.ancestor('tr').getData('uid'))
            .setStyles({
                height: height,
                width:  node.getComputedStyle(WIDTH)
            });

        // Add the new nodes to our floating table.
        floatingUserColumn.appendChild(containerNode);
    }, this);

    // Style the floating user container.
    floatingUserColumn.setStyles({
        left:       coordinates[0] + 'px',
        position:   'absolute',
        top:        coordinates[1] + 'px'
    });

    // Append to the grader region.
    this.graderRegion.append(floatingUserColumn);

    // Store a reference to this for later - we use it in the event handlers.
    this.userColumn = floatingUserColumn;
}

