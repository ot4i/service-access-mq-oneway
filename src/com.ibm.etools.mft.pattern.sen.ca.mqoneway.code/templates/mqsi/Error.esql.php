BROKER SCHEMA mqsi

/**
 * Copyright (c) 2014 IBM Corporation and other Contributors
 *
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *     IBM - initial implementation
**/

/**********************************************************
* ErrorAction UDP determines when an error occurs whether *
*		- the incoming message is rolled back			  *
*		- the incoming message is saved to an error queue *
*		- only included parametr is errorQueue            *
* ErrorNotification UDP determines whether a summary of   *
* the error is written to an error notification queue     *
*		- included if paramater errorNotification true    *
**********************************************************/

<?php
if ($_MB['PP']['pp12'] == 'true') {
echo "DECLARE ErrorNotification EXTERNAL BOOLEAN true; \n";
}

if ($_MB['PP']['pp11'] == 'error_queue') {
echo <<< ESQL
DECLARE ErrorAction EXTERNAL CHARACTER 'errorQueue';
CREATE FILTER MODULE CheckErrorAction
	CREATE FUNCTION Main() RETURNS BOOLEAN
	BEGIN
				IF ErrorAction = 'errorQueue' THEN RETURN TRUE; 
				ELSE
				RETURN FALSE;
				END IF;
	END;
	END MODULE;
ESQL;
}

if ($_MB['PP']['pp12'] == 'true') {

echo <<<ESQL
CREATE FILTER MODULE CheckErrorNotification
	CREATE FUNCTION Main() RETURNS BOOLEAN
	BEGIN
		-- Do not notify if this is a backed out message
		IF Root.MQMD.BackoutCount > 0 THEN RETURN FALSE; END IF;
		 RETURN ErrorNotification;
		END;
	END MODULE;
	
CREATE COMPUTE MODULE Build_Error_Notification
	CREATE FUNCTION Main() RETURNS BOOLEAN
	BEGIN
	
	SET OutputRoot.MQMD = InputRoot.MQMD;
	SET OutputRoot.Properties = NULL;
	Call AddExceptionData();
	END;
	CREATE PROCEDURE AddExceptionData() BEGIN
	CREATE NEXTSIBLING OF OutputRoot.MQMD DOMAIN('XMLNSC') NAME 'XMLNSC';
	SET OutputRoot.XMLNSC.Error.BrokerName  = SQL.BrokerName;
	DECLARE ERef REFERENCE TO OutputRoot.XMLNSC.Error;
	SET ERef.MessageFlowLabel = SQL.MessageFlowLabel;
    SET ERef.DTSTAMP =   CURRENT_TIMESTAMP;
    -- Save id of message in error  
	SET ERef.Message = InputRoot.MQMD.MsgId;
     
    -- Add some exception data for error and fault
		DECLARE Error INTEGER;
		DECLARE I INTEGER;
		DECLARE Text CHARACTER;
		DECLARE Label CHARACTER;
		SET I = 1;
		DECLARE K INTEGER;
		DECLARE start REFERENCE TO InputExceptionList.*[1];

		WHILE start.Number IS NOT NULL DO 
			SET Label = start.Label;
			SET Error = start.Number;
			IF Error = 3001 THEN
				SET Text = start.Insert.Text;
			ELSE
				SET Text = start.Text;
			END IF;
			-- Don't include the "Caught exception and rethrowing message"
			IF Error <> 2230 THEN
				-- Process inserts
				DECLARE Inserts Character;
				DECLARE INS Integer;
				SET Inserts = '';
				-- Are there any inserts for this exception
				IF EXISTS (start.Insert[]) THEN
					-- If YES add them to inserts string
				 	SET Inserts = Inserts || COALESCE(start.Insert[1].Text,'NULL')|| ' / ';
				 	SET K = 1;
				 	INSERTS: LOOP
						IF CARDINALITY(start.Insert[])> K 
						THEN 
							SET Inserts = Inserts || COALESCE(start.Insert[K+1].Text,'NULL')|| ' / ';
						-- No more inserts to process
						ELSE LEAVE INSERTS;
						END IF;
					SET K = K+1;
					END LOOP INSERTS;
				END IF;
				SET ERef.Exception[I].Label = Label;
				SET ERef.Exception[I].Error = Error;
				SET ERef.Exception[I].Text = Text;
				SET ERef.Exception[I].Inserts = COALESCE(Inserts, '');

				SET I = I+1; 
			END IF;
			-- Move start to the last child of the field to which it currently points
			MOVE start LASTCHILD;
		END WHILE;
				SET ERef.Fault = Environment.Pattern.fault;
    END;
END MODULE;

ESQL;
}
?>

CREATE COMPUTE MODULE Set_Properties
	CREATE FUNCTION Main() RETURNS BOOLEAN
	BEGIN
		SET OutputRoot = InputRoot;
		-- Remove domain info in properties. Must write a default BLOB because error may be parsing
		SET OutputRoot.Properties = NULL;
	END;
END MODULE;