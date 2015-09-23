# INTRODUCTION #
The IniFile class is aimed at providing support for .ini file management. It features :

- Reading a .ini file
- Retrieving section list, key list within a section and values.
- Defining new sections and keys
- Modifying sections and keys
- Writing back the .ini file, preserving comments and key/value alignment
- Using references to variables in the key values 

The key requirements for developing this class were :
- To be able to load a .ini file, change/create/delete some values or sections, and write the result back 
- To preserve the original formatting and comments when writing back the contents

# .INI FILE SYNTAX #

The IniFile class can process .ini files whose syntax is commonly defined on Windows platforms (and in the php.ini file...). It also provides support for extended notations which are described below. 

## BASIC SYNTAX ##
The basic syntax follows the Windows specifications :

- Entries are defined by key/value pairs separated by an equal sign ("=").
- Spaces are not significant between a key name and the equal sign
- Key/value pairs can be grouped in sections, whose name is enclosed in square brackets (eg, "[MySection]")
- Comments are introduced by a semicolon
- Key/value pairs found BEFORE the first section name are put in a section with an empty name

Section and key names are not case-sensitive.

## EXTENDED SYNTAX ##
The IniFile class accepts extended syntax with regards to the Windows implementation. The new syntactic items are described below. Note that this is a superset of the basic syntax, and cannot be disabled.

### COMMENTS ###
Comments can be either single-line or multiline :

- Single line comments are specified either by : ";" (.ini file style), "#" (shell style) or "//" (C++ style)
- Multiline comments are C-style : they start with the string "/\*" and end with "\*/". Note that nested multiline comments are authorized

### SPECIFYING A KEY AND A VALUE ###
Basically, a key/value pair is specified like this :

	[MySection]
    MySetting = setting value

The key name can include spaces :

	[MySection]
	My Setting = setting value

Note that the key "MySetting" will be different from "My Setting".

Empty values can be specified in two ways :

	[MySection]
	MySetting =
	MySetting

If you specify the same key twice in the same section, the second value will override the first one ; thus, the value of MySetting in the following example :

	[MySection]
	MySetting 	=  setting value 1
	MySetting 	=  setting value 2

will be "setting value 2". Note however that if you programmatically modify the value of "MySetting", there is no guarantee on which occurrence of "MySetting" will be actually modified.

Finally, multiline values can be specified as here-documents by adding the "<<" string after the equal sign :

	[MySection]
	MySetting1 	= <<
	this is the
	multiline value
	of mysetting1
	END

A keyword can be specified after the string "<<" :

	[MySection]
	MySetting1 	= <<STOP
	this is the
	multiline value
	of mysetting1
	STOP

The "END" keyword is expected when no keyword is specified after the here-document string.

The "<<<" string (as for PHP) can also be specified instead of "<<".

Spaces around the here-document string are ignored.

Note however that the end keyword must start at the beginning of the line ; no leading spaces are allowed.

### VARIABLES ###
When used with the VariableStore class, the IniFile class allows for specifying variable references in key values, such as in the following example :

	[MySection]
	SearchDirs 		=  ${PATH}

See "Using variable stores" in the "Using the IniFile class" section below on how to allow for specifying environment variables in .ini files.

### EXAMPLE .INI FILE ###
The following example shows a .ini file using all the extended features of the IniFile class :

	/***
		This .ini file gives examples on the extended syntax supported by the IniFile class.
	
		/* This is a nested multiline comment */
	 ***/

	; A comment
	# Another comment
	// and yet another comment

	[Variables]
	Root 				=  /
	Home 				=  $(HOME)

	[Settings]
	Display 			=  1
	Home 				=  $(Home)
	EmptyValue1			=
	EmptyValue2
	Key with spaces 	=  xxxx
	Heredoc1			=  <<<
		contents of
		heredoc1
	END
	Heredoc2 			=  <<STOP
		contents
		of
		heredoc2
	STOP


### DESIGN ISSUES ###
- Spaces before the key name, before and after the equal sign and after the value are not significant. If you want to have leading or trailing spaces 
- I have decided that absolutely anything AFTER the equal sign would be part of the key value ; for that reason, you cannot specify a single-line comment on the same line than a key/value pair 
- Flexibility has been priviledged over performance ; so, if you plan to use this class on .ini files that contain thousands of lines, consider other alternatives instead.
- There is no support for creating a .ini file from scratch ; you can however use the LoadFromString() method to create it from a string template.
- 