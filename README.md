# A better way to build Propel with HJSON Schema

This is a library to convert HJSON file to Propel XML schema.
I always found Propel(http://propelorm.org/) schema tedious to write. This should help make it more approchable.

# Install with composer
    composer require drez/hjson-to-propel-xml

# Live converter 
https://hjson2xml.apigoat.com

# Example 
Now you can write that:
```
{
    // database name
    goatcheese:
    {
        // custom behavior, not parameter
        add_validator:true,
        
        // timestamp behavior
        table_stamp_behavior:true,

        /* parameters for the APIgoat behavior from here, configurable custom behaviors shortcuts */
        set_debug_level:3,
        is_builder:true,
        add_hooks:{},
        with_api:true,
        /* to here */

        // table name='authy' decription='User'
        "authy('User')":{ 
            
            // parameters from the APIgoat behavior
            set_parent_menu:"Settings",
            
            // Primary column name=id_authy primaryKey=true autoincrement=true
            id_authy:["primary"],
            
            // default string column type=VARCHAR size=50
            validation_key:"string(32)",
            
            // Unique markup will be added for the table
            "username(Username)":["string(32)", "not-required", "unique"],
            
            // set the defaultValue=No
            is_root(Root):["enum(Yes, No)", "default:No"],

            // Add a default colunm, type=integer and add the foreign-key markup
            id_authy_group:["foreign(authy_group)", "required"],
            expire(Expiration): ["date()"]
        },
        authy_group_x:
        {
            // cross reference table, will add isCrossRef=true to the table
            is_cross_ref:true,

            // change the default settings on the foreign key
            id_authy:["foreign(authy)", "primary", "onDelete:cascade"],

            id_authy_group:["foreign(authy_group)", "primary"],
        }
    }
}
```
    
will translate to:

    <database name="goatcheese" defaultIdMethod="native" namespace="App" >
            <behavior name="add_validator" />
            <behavior name="table_stamp_behavior" />
            <behavior name="GoatCheese" >
                <parameter name="set_debug_level" value="3" />
                <parameter name="is_builder" value="1" />
                <parameter name="add_hooks" value="[]" />
                <parameter name="with_api" value="1" />
            </behavior>
        <table name="authy" description="User" >
            <behavior name="GoatCheese" >
                <parameter name="set_parent_menu" value="Settings" />
            </behavior>
            <column name="id_authy" type="INTEGER" size="11" required="true" primaryKey="true" autoIncrement="true" />
            <column name="validation_key" type="VARCHAR" size="32" required="false" />
            <column name="username" description="Username" type="VARCHAR" size="32" required="false" />
            <column name="is_root" description="Root" type="ENUM" valueSet="Yes, No" required="false" defaultValue="No" />
            <column name="id_authy_group" type="INTEGER" size="11" required="true" />
            <foreign-key foreignTable="authy_group" onDelete="restrict" onUpdate="restrict" >
                <reference local="id_authy_group" foreign="id_authy_group" />
            </foreign-key>
            <column name="expire" description="Expiration" type="DATE" required="false" />
            <unique >
                <unique-column name="username" />
            </unique>
        </table>
        <table name="authy_group_x" isCrossRef="true" >
            <column name="id_authy" type="INTEGER" size="11" required="true" primaryKey="true" />
            <foreign-key foreignTable="authy" onDelete="cascade" onUpdate="restrict" >
                <reference local="id_authy" foreign="id_authy" />
            </foreign-key>
            <column name="id_authy_group" type="INTEGER" size="11" required="true" primaryKey="true" />
            <foreign-key foreignTable="authy_group" onDelete="restrict" onUpdate="restrict" >
                <reference local="id_authy_group" foreign="id_authy_group" />
            </foreign-key>
        </table>
    </database>

# USE
    $text = file_get_contents($this->rootDir . DIRECTORY_SEPARATOR . $hjson_file);

    // make sure we have unix style text regardless of the input
    $std = mb_ereg_replace('/\r/', "", $text);
    $hjson = $cr ? mb_ereg_replace("\n", "\r\n", $std) : $std;
    
    // use of laktak/hjson(https://github.com/hjson/hjson-php) to convert the HJSON to array
    $parser = new \HJSON\HJSONParser();
    $obj = $parser->parse($hjson, ['assoc' => true]);

    // convert Hjson to Propel schema
    $HjsonToXml = new \HjsonToPropelXml\HjsonToPropelXml();
    $HjsonToXml->convert($obj);

# Schema

* You can use all Propel column type
* You can specify any Propel column attributes as attribut:value
* You are encouraged to add your own shortcuts for the most used type!

# TODO
* Make more keyword shortcut (String(32)), and find the best defaults!
* Propel validations
* Add table validations, warn on potential problems
* Add custom behavior validations
* Tests

License
----

MIT