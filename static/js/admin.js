pimcore.registerNS("pimcore.plugin.dynamicRoutes");

pimcore.plugin.dynamicRoutes = Class.create(pimcore.plugin.admin, {
    getClassName: function() {
        return "pimcore.plugin.dynamicRoutes";
    },

    initialize: function() {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params,broker){

        var user = pimcore.globalmanager.get("user");
        if (user.isAllowed("routes")) {

            // get the settings menu:
            var target      =  pimcore.globalmanager.get("layout_toolbar").settingsMenu,
                targetItems =  target.items,
                action      = new Ext.Action({
                    id:			"action_dynamicroutes_routes",
                    text:		t("Dynamic routes"),
                    iconCls:	"pimcore_icon_routes",
                    handler:	dynamicRoutes.buildPanel
                });

            var staticRouteMenuItem;
            targetItems.each(function(index, count){
                var menuItem = this;

                // IS this the static routes item?
                if(menuItem.text == t("static_routes")) {
                    // Create a menu item with the static and dynamic route items
                    // as subs
                    var routeMenu = {
                            id: "txtmenu_dynamicroutes_routing",
                            text: t("Routing"),
                            iconCls: "pimcore_icon_routes",
                            hideOnClick: false,
                            menu: [menuItem, action]
                        };

                    // save a reference to the static menu item
                    // it has to be hidden now and shown later because it's already
                    // in the list at this moment
                    staticRouteMenuItem = menuItem;
                    staticRouteMenuItem.setVisible(false);

                    // Add the new menu item at the place where the static route item
                    // once was
                    target.add(routeMenu);
                }
                // This is not the item we want, (re-)add it normally
                else {
                    target.add(menuItem);
                }
            });

            // Upon opening our new menu item, show the static route item which
            // should now be rendered in our new submenu instead of the "main"
            // one
            Ext.getCmp("txtmenu_dynamicroutes_routing").menu.addListener('render', function(){
                staticRouteMenuItem.setVisible(true);
            });
        }
    },

    buildPanel: function() {

		// create the holding panel
        dynamicRoutes.panel = new Ext.Panel({
            id			: "action_dynamicroutes_routes",
            title		: t('Dynamic routes'),
            iconCls		: "pimcore_icon_routes",
            border		: false,
            layout		: "fit",
            closable	: true,
            items		: [dynamicRoutes.buildGrid()],
			tools		: [{
				id	: 'help',
				qtip: t("dynamicRoutes_plugin_qtip")
			}]
        });

		// find the main tabpanel and add ours
        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
			tabPanel.add(dynamicRoutes.panel);
			tabPanel.activate(dynamicRoutes.panel.id);

		// show the panels by refreshing
        pimcore.layout.refresh();

		return dynamicRoutes.panel;
    },

	buildGrid: function () {

        var proxy = new Ext.data.HttpProxy({
				url: '/plugin/DynamicRoutes/routes/proxy'
			}),
			reader = new Ext.data.JsonReader(
				// the readers basic config
				{
					totalProperty: 'total',
					successProperty: 'success',
					root: 'data'
				},

				// deteermine the fields
				[
					{name: 'id'},
					{name: 'name',				allowBlank: true},

					// our fields
					{name: 'active',			allowBlank: true},
					{name: 'parent_id',			type:'int', allowBlank: true},
					{name: 'parent_module',		allowBlank: true},
					{name: 'parent_controller', allowBlank: true},
					{name: 'parent_action',		allowBlank: true},

					// like static routing
					{name: 'pattern',			allowBlank: false},
					{name: 'reverse',			allowBlank: true},
					{name: 'module',			allowBlank: true},
					{name: 'controller',		allowBlank: true},
					{name: 'action',			allowBlank: true},
					{name: 'variables',			allowBlank: true},
					{name: 'defaults',			allowBlank: true},
					{name: 'priority',			type:'int', allowBlank: true}
				]
			),
			writer = new Ext.data.JsonWriter(),
			itemsPerPage = 20;

		dynamicRoutes.documentsStore = new Ext.data.JsonStore({
			id			: 'store_dynamicroutes_documents',
			url			: '/plugin/DynamicRoutes/routes/documentlist',
			restful		: false,
			root		: "documents",
			fields		: [{name: "id"}, {name: "value"}]
		});

        dynamicRoutes.store = new Ext.data.Store({
            id			: 'store_dynamicroutes_routes',
            restful		: false,
            proxy		: proxy,
            reader		: reader,
            writer		: writer,
            remoteSort	: true,
            baseParams	: {
                limit: itemsPerPage,
                filter: ""
            },
            listeners: {
                write : function(store, action, result, response, rs) {}
            }
        });
        dynamicRoutes.store.load();

        dynamicRoutes.filterField = new Ext.form.TextField({
            xtype			: "textfield",
            width			: 200,
            style			: "margin: 0 10px 0 0;",
            enableKeyEvents	: true,
            listeners		: {
                "keydown" : function (field, key) {
                    if (key.getKey() == key.ENTER) {
                        var input = field;
                        dynamicRoutes.store.baseParams.filter = input.getValue();
                        dynamicRoutes.store.load();
                    }
                }.bind(this)
            }
        });

        dynamicRoutes.pagingtoolbar = new Ext.PagingToolbar({
            pageSize	: itemsPerPage,
            store		: dynamicRoutes.store,
            displayInfo	: true,
            displayMsg	: '{0} - {1} / {2}',
            emptyMsg	: t("no_objects_found")
        });
        dynamicRoutes.pagingtoolbar.add("-");
        dynamicRoutes.pagingtoolbar.add(new Ext.Toolbar.TextItem({text: t("items_per_page")}));
        dynamicRoutes.pagingtoolbar.add(new Ext.form.ComboBox({
            store : [
                [10, "10"],
                [20, "20"],
                [40, "40"],
                [60, "60"],
                [80, "80"],
                [100, "100"]
            ],
            mode			: "local",
            width			: 50,
            value			: 20,
            triggerAction	: "all",
            listeners		: {
                select: function (box, rec, index) {
                    dynamicRoutes.pagingtoolbar.pageSize = intval(rec.data.field1);
                    dynamicRoutes.pagingtoolbar.moveFirst();
                }.bind(this)
            }
        }));

        dynamicRoutes.editor = new Ext.ux.grid.RowEditor();

		var group = new Ext.ux.grid.ColumnHeaderGroup({
				rows: [[
					{header: "", colspan: 2},
					{header: t("parent"), colspan: 4, align: 'center', qtip: t("dynamicRoutes_plugin_parent_qtip")},
					{header: t("route"), colspan: 9, align: 'center'}
				]]
			}),
			typesColumns = [
				{header: t("active"),				width: 50, sortable: false, dataIndex: 'active', editor: new Ext.form.Checkbox({}), xtype: 'booleancolumn', trueText: t("yes"), falseText: t("no"), align: 'center'},
				{header: t("name"),					width: 50, sortable: true, dataIndex: 'name', editor: new Ext.form.TextField({})},

				// parent group
				{header: t("parent_id"),			width: 100, sortable: true, dataIndex: 'parent_id', editor: new Ext.form.ComboBox({
						store: dynamicRoutes.documentsStore,
						displayField: 'value',
						valueField: 'id',
						mode: "remote",
						triggerAction: "all"
					}),
					renderer: function(value){ return value == '0' ? t("none") : value}
				},
				{header: t("parent_module"),		width: 50, sortable: false, dataIndex: 'parent_module', hidden: true, hideable: true, editor: new Ext.form.TextField({})},
				{header: t("parent_controller"),	width: 50, sortable: false, dataIndex: 'parent_controller', editor: new Ext.form.TextField({})},
				{header: t("parent_action"),		width: 50, sortable: false, dataIndex: 'parent_action', editor: new Ext.form.TextField({})},

				// others group
				{header: t("pattern"),				width: 100, sortable: true, dataIndex: 'pattern', editor: new Ext.form.TextField({})},
				{header: t("reverse"),				width: 100, sortable: true, dataIndex: 'reverse', editor: new Ext.form.TextField({})},
				{header: t("module_optional"),		width: 50, sortable: false, dataIndex: 'module', hidden: true, hideable: true, editor: new Ext.form.TextField({}), tooltip: t("dynamicRoutes_plugin_module_qtip")},
				{header: t("controller"),			width: 50, sortable: false, dataIndex: 'controller', editor: new Ext.form.TextField({}), tooltip: t("dynamicRoutes_plugin_controller_qtip")},
				{header: t("action"),				width: 50, sortable: false, dataIndex: 'action', editor: new Ext.form.TextField({}), tooltip: t("dynamicRoutes_plugin_action_qtip")},
				{header: t("variables"),			width: 50, sortable: false, dataIndex: 'variables', editor: new Ext.form.TextField({})},
				{header: t("defaults"),				width: 50, sortable: false, dataIndex: 'defaults', editor: new Ext.form.TextField({})},
				{header: t("priority"),				width: 50, sortable: true, dataIndex: 'priority', editor: new Ext.form.ComboBox({
						store: [1,2,3,4,5,6,7,8,9,10],
						mode: "local",
						triggerAction: "all"
					})},
				{header: "", xtype: 'actioncolumn', width: 30, items: [{
						tooltip	: t('delete'),
						icon	: "/pimcore/static/img/icon/cross.png",
						handler	: function (grid, rowIndex) {
							dynamicRoutes.confirmDelete(function(){
							grid.getStore().removeAt(rowIndex);
							});
						}.bind(this)
					}]
				}
			];

        dynamicRoutes.grid = new Ext.grid.GridPanel({
            frame		: false,
            autoScroll	: true,
            store		: dynamicRoutes.store,
            columnLines	: true,
            stripeRows	: true,
            plugins		: [dynamicRoutes.editor, group],
            columns		: typesColumns,
            sm			: new Ext.grid.RowSelectionModel({singleSelect:true}),
            bbar		: dynamicRoutes.pagingtoolbar,
            tbar		: [
                {
                    text: t('add'),
                    handler: dynamicRoutes.onAdd.bind(this),
                    iconCls: "pimcore_icon_add"
                },
                '-',
                {
                    text: t('delete'),
                    handler: dynamicRoutes.onDelete.bind(this),
                    iconCls: "pimcore_icon_delete"
                },
                "->",
				{
                  text: t("filter") + "/" + t("search"),
                  xtype: "tbtext",
                  style: "margin: 0 10px 0 0;"
                },
                dynamicRoutes.filterField
            ],
            viewConfig: {
                forceFit: true
            }
        });

        return dynamicRoutes.grid;
    },


    onAdd: function (btn, ev) {
        var u = new dynamicRoutes.grid.store.recordType({
            name: ""
        });
        dynamicRoutes.editor.stopEditing();
        dynamicRoutes.grid.store.insert(0, u);
        dynamicRoutes.editor.startEditing(0);
    },

    onDelete: function () {
        var rec = dynamicRoutes.grid.getSelectionModel().getSelected();
        if (!rec) {
            return false;
        }
        dynamicRoutes.confirmDelete(function(){
        dynamicRoutes.grid.store.remove(rec);
		});
    },

	confirmDelete: function(callback) {
		// check for confirmation
		Ext.Msg.confirm(
			t("Are you sure?"),
			t("Are you sure?"),
			function(button){
				if(button == 'yes') callback();
    }
		);
	}

});

var dynamicRoutes = new pimcore.plugin.dynamicRoutes();