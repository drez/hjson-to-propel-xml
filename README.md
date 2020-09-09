# A better way to build Propel with HJSON Schema

This is a standalone library to convert HJSON file to Propel XML schema.
I always found Propel(http://propelorm.org/) schema tedious to write. This should help make it more approchable. HJSON(https://hjson.github.io) seems the best option.

Now you can write that:
```
{
    goatcheese: // database name
    {
        add_validator:true, // custom behavior, not parameter
        table_stamp_behavior:true, // timestamp behavior
        /* parameters for the APIgoat behavior from here */
        set_debug_level:3,
        is_builder:true,
        add_hooks:{},
        with_api:true,
        /* to here */

        "authy('User')":{ // table name='authy' decription='User'
            set_parent_menu:"Settings", // parameters from the APIgoat behavior
            id_authy:["primary"], // Primary column name=id_authy primaryKey=true autoincrement=true
            validation_key:"string(32)", // default string column type=VARCHAR size=50
            "username(Username)":["string(32)", "not-required", "unique"], // Unique markup will be added for the table
            is_root(Root):["enum(Yes, No)", "default:No"], // set the defaultValue=No
            id_authy_group:["foreign(authy_group)", "required"], // Add a default colunm, type=integer and add the foreign-key markup
            expire(Expiration): ["date()"]
        },
        authy_group_x:
        {
            is_cross_ref:true,  // cross reference table, will add isCrossRef=true to the table
            id_authy:["foreign(authy)", "primary", "onDelete:cascade"], // change the default settings on the foreign key
            id_authy_group:["foreign(authy_group)", "primary"],
        }
    }
}
```
    
And it will translate to:

    <database name="" defaultIdMethod="native" namespace="App" />
            <behavior name="add_validator" />
            <behavior name="table_stamp_behavior" />
            <behavior name="GoatCheese" >
                <parameter set_debug_level="3" />
                <parameter is_builder="true" />
                <parameter add_hooks="[]" />
                <parameter with_api="true" />
            <behavior />
        <table name="authy" description="User" >
            <behavior name="GoatCheese" >
                <parameter set_parent_menu=""Settings"" />
            <behavior />
            <column name="id_authy" type="INTEGER" size="11" required="true" setNull="false" primaryKey="true" autoIncrement="true" />
            <column name="validation_key" type="VARCHAR" size="32" required="false" setNull="true" />
            <column name="username" description="Username" type="VARCHAR" size="32" required="false" setNull="true" />
            <column name="is_root" description="Root" type="ENUM" valueSet="Yes, No" required="false" setNull="false" defaultValue="No" />
            <column name="id_authy_group" type="INTEGER" size="11" required="true" setNull="true" />
            <foreign-key foreignTable="authy_group" onDelete="restrict" onUpdate="restrict" >
                <reference local="id_authy_group" foreign="id_authy_group" >
            <foreign-key />
            <column name="expire" description="Expiration" type="DATE" required="false" setNull="true" />
            <unique >
                <unique-column name="username" />
            <unique />
        <table />
        <table name="authy_group_x" isCrossRef="true" >
            <column name="id_authy" type="INTEGER" size="11" required="true" setNull="true" primaryKey="true" />
            <foreign-key foreignTable="authy" onDelete="cascade" onUpdate="restrict" >
                <reference local="id_authy" foreign="id_authy" >
            <foreign-key />
            <column name="id_authy_group" type="INTEGER" size="11" required="true" setNull="true" primaryKey="true" />
            <foreign-key foreignTable="authy_group" onDelete="restrict" onUpdate="restrict" >
                <reference local="id_authy_group" foreign="id_authy_group" >
            <foreign-key />
        <table />
    <database />

# USE
    $text = file_get_contents($this->rootDir . DIRECTORY_SEPARATOR . $hjson_file);
    $std = mb_ereg_replace('/\r/', "", $text); // make sure we have unix style text regardless of the input
    $hjson = $cr ? mb_ereg_replace("\n", "\r\n", $std) : $std;
    $parser = new \HJSON\HJSONParser();
    $obj = $parser->parse($hjson, ['assoc' => true]);
    $HjsonToXml = new \HjsonToPropelXml\HjsonToPropelXml();
    $HjsonToXml->convert($obj);

# TODO
* Make more keyword shortcut (String(32)), and find the best defaults
* Propel validations
* Support for ALL propel parameters, right now only the most used are supported
* Add table validations, warn on potential problems
* Add custom behavior validations
* Tests

License
----

MIT