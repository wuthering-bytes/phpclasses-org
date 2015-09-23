<?php
/**************************************************************************************************************

    NAME
        Variables.phpclass

    DESCRIPTION
        Variable expansion classes.
  
 	To correctly work, variable expansion must be achieved using 3 classes :
 	- A VariableStore object, which holds variable name/value pairs.
 	- Each entry in the variable store is either of type Variable or EnvironmentVariable. The latter 
 	  ensures that each value update is reflected in the environment variables list.
 	- A VariableParser object (or derived), to be associated with a variable store. This class implements
 	  the methods needed to parse a variable reference.
 
 	A typical usage may be the following :
 		$store = new VariableStore ( ) ;
		$store -> Define ( 'titi', 'titi value' ) ;
		$store -> Define ( 'toto', 'toto value' ) ;
		$store -> Define ( 'tutu', 'tutu value' ) ;
		output ( "[" . $store -> Expand ( '$titi $toto ${tutu}' ) . "]" ) ;

 	A variable store uses by default a ShellVariableParser object, which allows for the following variable
 	references :
 		$name
 		${name}
 		$(name)
  
 	The first form allows for variable names made of letters, numbers, underlines, dashes and dots.
 	The second and three forms allow to specify any character in the variable name.
  
 	Variable values can in turn contain references to other variables, unless the OPTION_RECURSIVE flag
 	is not specified when instanciating the variable store object.
  
 	Provisions are made to specify extra data after a variable name (although not yet implemented by the
 	VariableParser class) ; in the future, the following syntaxes will be accepted :
  
 		${name:extra data}
 		$($name:extra data) 

    AUTHOR
        Christian Vigh, 12/2014.

    HISTORY
    [Version : 1.0]	[Date : 2014/12/02]     [Author : CV]
        Initial version.

    [Version : 1.0.1]	[Date : 2014/12/06]     [Author : CV]
	. Added the $accept_escapes parameter to VariableStore::Expand().

    [Version : 1.0.2]	[Date : 2015/04/08]     [Author : CV]
 	. Corrected a bug in the __Expand() method that caused nested variable references to be incorrectly
 	  diagnosed as recursive references because the $call_stack array was not cleaned up after an inner
 	  call to _Expand().
 
 **************************************************************************************************************/
namespace  Thrak\Processors ;

defined ( '__THRAK_SETUP__' ) or die ( "This file cannot be accessed directly." ) ;


/*==============================================================================================================

    Variable class -
        Implements a class for holding a variable name together with its value.

  ==============================================================================================================*/
class  Variable		extends  Object
   {
	protected	$Name ;
	protected	$Value		=  null ;
	
	
	/*==============================================================================================================
	
	    NAME
	        Constructor - Instanciates a variable.
	
	    PROTOTYPE
	        $variable	=  new Variable ( $name, $value ) ;
	
	  ==============================================================================================================*/
	public function  __construct ( $name, $value )
	   {
		$this -> Name	=  $name ;
		$this -> Value	=  $value ;
	    }
	

	/*==============================================================================================================
	
	    Getters/setters for the Name and Value properties -
	        Implement access to the variable properties Name and Value. 
		The protected methods GetValue() and SetValue() are called to retrieve or set the variable value. This
		allows for derived classes to override them for providing a specialized behavior, such as in the
		EnvironmentVariable class.
		The Name property is readonly.
	
	  ==============================================================================================================*/
	public function  __get ( $name )
	   {
		if  ( ! strcasecmp ( $name, "Name" ) )
			return ( $this -> Name ) ;
		else if  ( ! strcasecmp ( $name, "Value" ) )
			return ( $this -> GetValue ( ) ) ;
		else
			error ( new \Thrak\System\BadPropertyException ( "Undefined property " . get_class ( $this ) . "::$name." ) ) ;
	    }

	
	public function  __set ( $name, $value )
	   {
		if  ( ! strcasecmp ( $name, "Name" ) )
			error ( new \Thrak\System\BadPropertyException ( "Property " . get_class ( $this ) . "::$name is readonly." ) ) ;
		else if  ( ! strcasecmp ( $name, "Value" ) )
			$this -> SetValue ( $value ) ;
		else
			error ( new \Thrak\System\BadPropertyException ( "Undefined property " . get_class ( $this ) . "::$name." ) ) ;
	    }
	
	
	/*==============================================================================================================
	
	    GetValue / SetValue -
	        Access to the variable value. These methods can be overridden by derived classes to provide specialized
		behavior.
	
	  ==============================================================================================================*/
	protected function  GetValue ( )
	   { return ( $this -> Value ) ; }
	
	
	protected function  SetValue ( $value ) 
	   { $this -> Value	=  $value ; }
	
	
	/*==============================================================================================================
	
	    __tostring -
	        Returns the variable value.
	
	  ==============================================================================================================*/
	public function  __tostring ( )
	   { return ( $this -> GetValue ( ) ) ; }
    }


/*==============================================================================================================

    EnvironmentVariable -
        Implements a variable object that synchronizes its value with its corresponding environment variable.

  ==============================================================================================================*/
class  EnvironmentVariable		extends  Variable
   {
	/*==============================================================================================================
	
	    Constructor -
	        Creates (or synchronizes with) an environment variable.
	
	  ==============================================================================================================*/
	public function  __construct ( $name, $value = null )
	   {
		parent::__construct ( $name, $value ) ;
		
		if  ( $value  ===  null )
			$this -> GetValue ( ) ;
	    }
	
	
	/*==============================================================================================================
	
	    Destructor -
	        Unsets the corresponding environment variable.
	
	  ==============================================================================================================*/
	public function  __destruct ( )
	   {
		putenv ( $this -> Name ) ;
		unset ( $_ENV [ $this -> Name ] ) ;
	    }
	
	
	/*==============================================================================================================
	
	    GetValue -
	        Returns the variable value. Get this value from environment if not yet initialized.
	
	  ==============================================================================================================*/
	protected function  GetValue ( )
	   {
		if  ( $this -> Value  ===  null )
		   {
			$value		=  getenv ( $this -> Name ) ;
		
			if  ( $value  ===  false )
				$value	=  "" ;
		    }
		else
			$value	=  $this -> Value ;
		
		$this -> Value	=  $value ;
		
		return ( $value ) ;
	    }
	
	
	/*==============================================================================================================
	
	    SetValue -
	        Sets the variable value. Updates the environment and $_ENV array.
		If null is specified, the value is removed from the environment.
	
	  ==============================================================================================================*/
	protected function  SetValue ( $value )
	   {
		if  ( $value  ===  null )
		   {
			putenv ( $this -> Name ) ;
			unset ( $_ENV [ $this -> Name ] ) ;
		   }
		else
		   {
			putenv ( "{$this -> Name}=$value" ) ;
			$_ENV [ $this -> Name ]		=  $value ;
		    }
		
		$this -> Value	=  $value ;
	    }
    }


/*==============================================================================================================

    VariableParser -
        Implements a generic parser for variable references.
 	This class cannot be instanciated ; only derived classes can be. They must provide the following 
 	parameters to the constructor :
 	- The character that starts a reference to a variable ('$' for example). This is for optimization purposes.
 	- A regular expression that matches a variable reference.
  
 	They must in turn implement the GetMatchResults() method, which must return an associative array with
 	the following entries :
 	'name' -
 		Name of the variable which is referenced.
 	'extra' -
 		Extra data after the variable reference and the closing delimiter.
 	'next' -
 		Index of the first character after the variable reference.

  ==============================================================================================================*/
abstract class  VariableParser		extends  Object 
   {
	public		$Regex ;
	public		$PrefixCharacter ;
	
	
	/*==============================================================================================================
	
	    Constructor -
	        Builds a VariableParser object using the specified start character and regular expression.
		The regular expression must not contain delimiters, since a surrounding <regex> capturing expression
		will be added.
	
	  ==============================================================================================================*/
	public function  __construct ( $prefix, $regex )
	   {
		parent::__construct ( ) ;
		
		$this -> PrefixCharacter	=  $prefix ;
		
		$regex				=  "#(?P<regex> $regex)#imsx" ;
		$this -> Regex			=  $regex ;
	    }

	
	protected abstract function  GetMatchResults ( $matches ) ;
	
	
	/*==============================================================================================================
	
	    Parse -
	        Parses a variable definition, starting at the specified character offset of $value.
	
	  ==============================================================================================================*/
	public function  Parse ( $value, $offset )
	   {
		// Offset past the end of string or character at specified offset not equal to the prefix character
		if  ( ! isset ( $value [ $offset ] )  ||  $value [ $offset ]  !=  $this -> PrefixCharacter )
			return ( false ) ;

		// Data at curret offset does not match the regular expression denoting a variable reference
		if  ( ! preg_match ( $this -> Regex, $value, $matches, PREG_OFFSET_CAPTURE, $offset ) )
			return ( false ) ;
		
		// Regular expression match, but not at the desired offset
		if  ( $matches [ 'regex' ] [1]  !=  $offset )
			return ( false ) ;
		
		// All tests successfully passed, get the match results
		$result		=  $this -> GetMatchResults ( $matches ) ;

		return ( $result ) ;
	    }
    }


/*==============================================================================================================

    ShellVariableParser -
        Implements a Unix-style variable reference parser.

  ==============================================================================================================*/
class  ShellVariableParser		extends  VariableParser 
   {
	/*==============================================================================================================
	
	    Constructor -
	        Builds a VariableParser object which allows for the following variable reference syntaxes :
	 		$name
	 		${name}
	 		$(name)
	
	  ==============================================================================================================*/
	public function  __construct ( )
	   {
		static	$regex	=  '
					(?P<form1>
						\$
						(?P<name1>
							[\w\-.]+
						 )
					 )
					|
					(?P<form2>
						\$ \{
						(?P<name2>
							[^}]+
						 )
						\}
					 )
					|
					(?P<form3>
						\$ \(
						(?P<name3>
							[^)]+
						 )
						\)
					 )
				    ' ;
		
		parent::__construct ( '$', $regex ) ;
	    }
	
	
	/*==============================================================================================================
	
	    GetMatchResults -
	        Extract correct data from the supplied preg_match() results.
	
	  ==============================================================================================================*/
	protected function  GetMatchResults ( $matches ) 
	   {
		$name	=  null ;
		$extra	=  '' ;
		
		if  ( $matches [ 'name1' ] [0] )
			$name	=  $matches [ 'name1' ] [0] ;
		else if  ( $matches [ 'name2' ] [0] )
			$name	=  $matches [ 'name2' ] [0] ;
		else if  ( $matches [ 'name3' ] [0] )
			$name	=  $matches [ 'name3' ] [0] ;
		
		if  ( $name  ===  null )
			return ( false ) ;
		
		$offset		=  strlen ( $matches [ 'regex' ] [0] ) ;
		
		return ( [ 'name' => $name, 'extra' => $extra, 'next' => $offset ] ) ;
	    }
    }


/*==============================================================================================================

    VariableStore class -
        Implements a pool of variables, which can come from the environment.

  ==============================================================================================================*/
class  VariableStore		extends		Object
				implements	\ArrayAccess, \Countable, \IteratorAggregate
   {
	// Variable store options
	const		OPTION_NONE				=  0x0000 ;		// No option
	const		OPTION_DEFAULT				=  0x8000 ;		// Default : case-insensitive (Windows platforms only), warn if undefined and recursive
	const		OPTION_CASE_INSENSITIVE			=  0x0001 ;		// Variable names are case-insensitive
	const		OPTION_USE_ENVIRONMENT_VARIABLES	=  0x0002 ;		// Use environment variables
	const		OPTION_WARN_IF_UNDEFINED		=  0x0004 ;		// Warn if undefined variables are referenced
	const		OPTION_RECURSIVE			=  0x0008 ;		// Recursively process variable values to further search for references
	
	// Current variable store options
	protected	$Options ;
	// Array of variables
	protected	$Variables ;
	// Variable reference parser object
	protected	$Syntax ;
	

	/*==============================================================================================================
	
	    NAME
	        Constructor - Builds a VariableStore object.
	
	    PROTOTYPE
	        $store	=  new VariableStore ( $options, $syntax ) ;
	
	    DESCRIPTION
	        Builds a variable store.
	
	    PARAMETERS
	        $options (integer) -
	                A combination of one or more of the following values :
	 		- OPTION_NONE :
	 			No special processing option required.
	  
	 		- OPTION_CASE_INSENSITIVE :
	 			Variable names are case-insensitive.
	  
	 		- OPTION_USE_ENVIRONMENT_VARIABLES :
	 			When specified, environment variables (and $_ENV array entries) are included in the 
	 			variable store. They are also update if a variable value is updated.
	  
	 		- OPTION_WARN_IF_UNDEFINED :
	 			Warnings are issued if an undefined variable is not defined in this variable store.
	  
	 		- OPTION_RECURSIVE :
	 			Variable references are recursively processed in variable values.
	  
	 		- OPTION_DEFAULT :
	 			Enables the OPTION_WARN_IF_UNDEFINED and OPTION_RECURSIVE options.
	 			On Windows platforms, the OPTION_CASE_INSENSITIVE flag is also enabled.
				The OPTION_DEFAULT flag can be specified with other flags.
	  
	 	$syntax (object) -
	 		An object inheriting from VariableParser.
	 		If not specified, a ShellVariableParser object will be used.
	
	  ==============================================================================================================*/
	public function  __construct ( $options = self::OPTION_DEFAULT, $syntax = null )
	   {
		parent::__construct ( ) ;
	
		// Creates a variable parser, if not specified
		if  ( ! $syntax )
			$syntax		=  new ShellVariableParser ( ) ;
		
		// Provide default options if needed
		if  ( $options  &  self::OPTION_DEFAULT )
		   {
			// On Windows platform, for default options, variable names are case-insensitive
			if  ( IS_WINDOWS )
				$options		|=  self::OPTION_CASE_INSENSITIVE ;
			
			$options	|=  self::OPTION_WARN_IF_UNDEFINED | self::OPTION_RECURSIVE ;
		    }
		
		// Create the array to store variables
		if  ( $options  &  self::OPTION_CASE_INSENSITIVE )
			$this -> Variables	=  new  AssociativeArray ( ) ;
		else
			$this -> Variables	=  [] ;
		
		// If environment variables are to be used, populate the variable store with the contents of the $_ENV array
		if  ( $options  &  self::OPTION_USE_ENVIRONMENT_VARIABLES )
		   {
			foreach  ( $_ENV  as  $name => $value )
			   {
				$this -> Variables [ $name ]	=  new EnvironmentVariable ( $name ) ;
			    }
		    }
		
		// All done, save input parameters
		$this -> Options	=  $options ;
		$this -> Syntax		=  $syntax ;
	    }
	
	
	/*==============================================================================================================
	
	    NAME
	        Define - Defines a variable.
	
	    PROTOTYPE
	        $store -> Define ( $name, $value, $export = false ) ;
	
	    DESCRIPTION
	        Defines a variable.
	
	    PARAMETERS
	        $name (string) -
	                Name of the variable to be defined.
	  
	 	$value (any) -
	 		Either an object inheriting from the Variable class, or a value.
	  
	 	$export (boolean) -
	 		When true, the defined variable will also be exported to the environment (only when $value is
	 		not an instance from the Variable class).
	
	  ==============================================================================================================*/
	public function  Define  ( $name, $value = null, $export = false )
	   {
		if  ( isset ( $this [ $name ] ) ) 
			unset ( $this [ $name ] ) ;
		
		if  ( is_a ( $value, '\Thrak\Processors\Variable' ) )
			$new_value	=  $value ;
		else if  ( $export )
			$new_value	=  new EnvironmentVariable ( $name, $value ) ;
		else
			$new_value	=  new Variable ( $name, $value ) ;
		
		$this [ $name ]		=  $new_value ;
	    }


	/*==============================================================================================================
	
	    NAME
	        Expand - Expands a variable value.
	
	    PROTOTYPE
	        $str	=  $store -> Expand ( $value, $accept_escapes = false ) ;
	
	    DESCRIPTION
	        Expands a value referencing variables. The following variable store flags influence variable expansion :
	  
	 	- OPTION_CASE_INSENSITIVE :
	 		Specifies whether variable names are case-sensitive or not.
	  
	 	- OPTION_WARN_IF_UNDEFINED :
	 		Outputs a warning if an undefined variable is referenced in the supplied value.
	  
	 	- OPTION_RECURSIVE :
	 		Variable references are recursively processed after expansion.
	
	    PARAMETERS
	        $value (string) -
	                Value to be expanded.
	  
	 	$accept_escapes (string) -
	 		When true, a backslash before a character will return this character as is.
	
	    RETURN VALUE
	        The expanded value. Unexisting variable references are replaced by an empty string.
	
	  ==============================================================================================================*/
	public function  Expand ( $value, $accept_escapes = false )
	   {
		if  ( $this -> Options  &  self::OPTION_CASE_INSENSITIVE )
			$call_stack	=  new AssociativeArray ( ) ;
		else
			$call_stack	=  [] ;
		
		return ( $this -> __Expand ( $value, $accept_escapes, $call_stack ) ) ;
	    }
	
	
	private function  __Expand ( $value, $accept_escapes, $call_stack )
	   {
		$length		=  strlen ( $value ) ;
		$syntax		=  $this -> Syntax ;
		$result		=  "" ;
		
		// Loop through input value characters
		for  ( $i = 0 ; $i  <  $length ; $i ++ )
		   {
			$ch	=  $value [$i] ;
			
			// Variable references will not be expanded if preceded by a backslash
			if  ( $ch  ==  '\\'  &&  $accept_escapes )
			   {
				$result	.=  $ch ;
				
				if  ( $i + 1  <  $length )
					$result		.=  $value [++$i] ;
			    }
			// Potential variable reference found
			else if  ( $ch  ==  $syntax -> PrefixCharacter )
			   {
				// Parse it
				$vref	=  $syntax -> Parse ( $value, $i ) ;
				
				// Not a variable reference - ignore this character
				if  ( $vref  ===  false )
					$result		.=  $ch ;
				// A variable reference has been found
				else
				   {
					$name	 =  $vref [ 'name' ] ;
					$extra	 =  $vref [ 'extra' ] ;
					$i	+=  $vref [ 'next' ] - 1 ;

					// Handle circular variable references
					if  ( isset ( $call_stack [ $name ] ) )
					   {
						error ( new \Thrak\System\RuntimeException ( "Circular variable reference in \$$name." ) ) ;
					    }
					
					// If the variable is defined, process its value
					if  ( isset ( $this [ $name ] ) ) 
					   {
						$this_value		 =  $this [ $name ] ;
						
						// ... and even recursively, if the OPTION_RECURSIVE flag is specified
						if  ( $this -> Options  &  self::OPTION_RECURSIVE )
						   {
							$call_stack [ $name ]	 =  $name ;
							$result			.=  $this -> __Expand ( $this_value -> Value, $accept_escapes, $call_stack ) ;
							$call_stack -> Pop ( ) ;
						    }
						// Otherwise, simply expand the value as is
						else
							$result		.=  $this_value -> Value ;
					    }
					// Undefined variable, issue a warning if requested
					else if  ( $this -> Options  &  self::OPTION_WARN_IF_UNDEFINED )
						warning ( "Reference to undefined variable \"$name\"." ) ;
				    }
			    }
			// Other cases : simply collect the character
			else
				$result		.=  $ch ;
		     }
		
		// All done, return
		return ( $result ) ;
	    }
	
	
	/*==============================================================================================================
	
	    NAME
	        Export - Exports a variable from the variable store.
	
	    PROTOTYPE
	        $store -> Export ( $name ) ;
	
	    DESCRIPTION
	        Exports a variable from the variable store to the environment. If the variable does not exist, it will
	 	be created with an empty value.
	
	    PARAMETERS
	        $name (string) -
	                Name of the variable to be exported.
	
	  ==============================================================================================================*/
	public function  Export ( $name )
	   {
		if  ( ! isset ( $this [ $name ] ) )
			$variable	=  new  EnvironmentVariable ( $name, "" ) ;
		else
			$variable	=  $this [ $name ] ;
		
		if  ( ! is_a ( $variable, '\Thrak\Processors\EnvironmentVariable' ) )
		   {
			$new_variable	=  new EnvironmentVariable ( $variable -> Name, $variable -> Value ) ;
			$this [ $name ]	=  $new_variable ;
		    }
	    }
	

	/*==============================================================================================================
	
	    NAME
	        IsDefined - Checks if a variable is defined.
	
	    PROTOTYPE
	        $bool	=  $store -> IsDefined ( $name ) ;
	
	    DESCRIPTION
	        Checks if the specified variable is defined.
	
	    PARAMETERS
	        $name (string) -
	                Name of the variable whose existence is to be checked.
	
	    RETURN VALUE
	        True if the specified variable is defined in this variable store, false otherwise.
	
	  ==============================================================================================================*/
	public function  IsDefined ( $name )
	   { return ( isset ( $this [ $name ] ) ) ; }
	
	
	/*==============================================================================================================
	
	    NAME
	        Undefine - Undefines a variable.
	
	    PROTOTYPE
	        $store -> Undefine ( $name ) ;
	
	    DESCRIPTION
	        Undefines a variable.
	
	    PARAMETERS
	        $name (string) -
	                Name of the variable to be undefined.
	
	  ==============================================================================================================*/
	public function  Undefine ( $name )
	   {
		unset ( $this [ $name ] ) ;
	    }
	

	
	/*==============================================================================================================
	
	        ArrayAccess, Countable and IteratorAggregate interfaces implementations.
	
	  ==============================================================================================================*/
	public function  Count ( )
	   { return ( count ( $this -> Variables ) ) ; }
	
	
	public function  getIterator ( )
	   { return ( $this -> Variables -> getIterator ( ) ) ; }
	
	
	public function  offsetExists ( $offset )
	   { return ( isset ( $this -> Variables [ $offset ] ) ) ; }
	
	
	public function  offsetGet ( $offset )
	   { return ( $this -> Variables [ $offset ] ) ; }
	

	// OffsetSet has a special behavior - see comments inline
	public function  offsetSet ( $offset, $value )
	   {
		$is_var		=  is_a ( $value, '\Thrak\Processors\Variable' ) ;
			
		// If the variable already exists...
		if  ( isset ( $this -> Variables [ $offset ] ) ) 
		   {
			// If the supplied value is a Variable object (or descendant), only take its string value
			if  ( $is_var )
				$this -> Variables [ $offset ] -> Value		=  $value -> Value ;
			else
				$this -> Variables [ $offset ] -> Value		=  $value ;
		    }
		// Otherwise create it
		else
		   {
			// Creation is not needed if the supplied value is already a Variable object
			if  ( $is_var )
				$this -> Variables [ $offset ]			=  $value ;
			else
				$this -> Variables [ $offset ]			=  new Variable ( $offset, $value ) ;
		    }
	    }

	
	public function  offsetUnset ( $offset )
	   { unset ( $this -> Variables [ $offset ] ) ; }
    }