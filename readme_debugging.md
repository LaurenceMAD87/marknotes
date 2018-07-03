# How to enable the debugging mode

If you're a developer, you can obtain a lot of information about marknotes and what he does by enabling the debug mode.

You'll find in the `settings.json.dist` file properties for this :

```json
{
	"debug": {
		"enabled": 0,
		"development": 0,
		"logfile": {
			"template": "[%level_name%] %message% %context%"
		},
		"output": {
			"debug": 1,
			"info": 1,
			"notice": 1,
			"warning": 1,
			"error": 1,
			"critical": 1,
			"alert": 1,
			"emergency": 1
		}
	}
}
```

There are two boolean : `enabled` and ´development`.

`development` is the higher mode, when extra informations are displayed or sent to the debug log file. For some actions like Ajax requests, when the `development` mode is set, the output can differ (more information) and can be stopped in case of problems (f.i. when notes are exported to a specific format like `.docx`)

## Enabling the debug mode

Enabling the normal debugging mode is thus, simply, set `enabled` to the value of 1.

```json
{
	"debug": {
		"enabled": 0
	}
}
```

*You can, of course, enable the debug mode from the settings screen of marknotes.*

## Location of the debug file

You'll find the debug file in the `/tmp` folder of your marknotes website. The name of file is `debug.log`.

This file can be removed without a problem, it'll be created every time marknotes will be called; even for an Ajax request.

The file will be created each time : there is no append to the file; a log is one action.

## The other properties

marknotes use the [monolog library](https://github.com/Seldaek/monolog) for outputting strings in the log file.

The sentence will have a specific layout as described [here library](https://github.com/Seldaek/monolog/blob/master/doc/message-structure.md). You can choose to mention the date/time, the username, ...

The standard template is : `[%level_name%] %message% %context%` where %message% is the placeholder when the real debug sentence will appear.

If you want something else, you can modify the `template` property.

```json
{
	"debug": {
		"logfile": {
			"template": "[%level_name%] %message% %context%"
		}
	}
}
```

The last properties are, in fact, filters : you can choose to output only errors message and not "pollute" the debug file with debug / info / notice or warning messages, you just want to see errors.

If it's your choose, look the code below : we've filter message until error, the first one who will be displayed in our log file.

```json
{
	"debug": {
		"output": {
			"debug": 0,
			"info": 0,
			"notice": 0,
			"warning": 0,
			"error": 1,
			"critical": 1,
			"alert": 1,
			"emergency": 1
		}
	}
}
```

## Debug interface

You can also use the DEBUG interface by adding /DEBUG to your URL so, instead of going to http://localhost/notes visit http://localhost/notes/DEBUG.

DEBUG is indeed the name of a subfolder of marknotes. You'll find there a index.php file that will display an interface to help you to debug marknotes like f.i. an interface for reading the debug.log file.
