-- ----------------------------
--  Procedure structure for `SMAWSP_ADD_ITEMS_TO_ORDER`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADD_ITEMS_TO_ORDER`;
delimiter ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `SMAWSP_ADD_ITEMS_TO_ORDER`(IN in_order_id int(11), IN in_skin_id int(11), IN xraw_stamp VARCHAR(255), OUT out_return_id INT(11) ,OUT out_message varchar(255))
BEGIN
  DECLARE xuser_found INT (1);
	DECLARE xemail varchar(100);
	DECLARE xorder_id INT (11);
	DECLARE xorder_type CHAR(1);
	DECLARE xuser_id int(11);
	DECLARE xbalance DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xcustomer_donation_type CHAR(1) DEFAULT 'X';
	DECLARE xcustomer_donation_amt DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xorder_detail_id int(11);
	DECLARE xitem_id int(11);
	DECLARE xitem_external_id VARCHAR(50) DEFAULT NULL;  -- used for sending to a POS integration

	DECLARE xmenu_id int(11);
	DECLARE xmenu_version DECIMAL(10,2);
	DECLARE xmenu_merchant_id int(11);
	DECLARE xmenu_item_owner int(11);  -- used to make sure the item is owned by the merchant that is identified in the order
	DECLARE xitem_quantity int(11);
	DECLARE xmenu_type_name VARCHAR(50);
	DECLARE xmenu_type_active CHAR(1);
	DECLARE xmenu_type_id int(11);
	DECLARE xsize_id int(11);
	DECLARE xsizeprice_id int(11);
	DECLARE xsize_name  VARCHAR(100);
	DECLARE xsize_print_name  VARCHAR(100);
	DECLARE xitem_name  VARCHAR(100);
	DECLARE xitem_active CHAR(1);
	DECLARE xitem_print_name  VARCHAR(100);
	DECLARE xitem_price  DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_tax_group INT(1) DEFAULT 1;
	DECLARE xitem_tax_rate DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_price_active CHAR(1);
	DECLARE xitem_sub_total  DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_nameofuser VARCHAR(100); -- used to attach a name to an order item
	DECLARE xitem_note VARCHAR(100); -- used to attach a note to a particular order item
	DECLARE xitem_external_detail_id VARCHAR(255);
	DECLARE xquantity int(11) DEFAULT 1; -- total numver of items in the order
	DECLARE xnote varchar(250) DEFAULT ''; -- note for entire order
	DECLARE xitem_points_used INT(11) DEFAULT 0;
	DECLARE xitem_amount_off_from_points  DECIMAL(10,2) DEFAULT 0.0;
	DECLARE xtrans_fee_amt DECIMAL(10,3) DEFAULT 0.25;  -- we will actually get this from teh merchant object
	DECLARE xsub_total DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xtax_total_rate DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xtax_total_amt DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xtax_running_total_amt DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xfixed_tax_amount DECIMAL(6,2) DEFAULT 0.0;
	DECLARE xstatus CHAR(1) DEFAULT 'O';  -- order status
	DECLARE xgrand_total DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xucid VARCHAR(255);

	-- skin stuff
	DECLARE xskin_donation_active CHAR(1) DEFAULT 'N';
	DECLARE xskin_in_production CHAR(1);
	DECLARE xbrand_id int(11);
	DECLARE charge_modifiers boolean DEFAULT TRUE;

	DECLARE xnumeric_id int(11);
	DECLARE xmerchant_id INT (11);
	DECLARE xmerchant_name VARCHAR(50);
	DECLARE xmerch_donate_amt DECIMAL(10,3) DEFAULT 0.000;
	DECLARE linebuster CHAR(1) DEFAULT 'N';

	DECLARE found int(11)  DEFAULT 0;
	DECLARE lto_found int(11)  DEFAULT 0;
	DECLARE row_count int(11) default  0;
	DECLARE xlogical_delete CHAR(1);

	-- temp table stuff
	DECLARE xtemp_order_detail_id int(11);
	DECLARE xnum_of_temp_order_items int(11);
	DECLARE xtemp_order_item_mod_id int(11);
	DECLARE xcalced_order_total DECIMAL(10,3) DEFAULT 0.0; -- USING THIS TO TEST HTE APPS MATH ON ORDER CALC.  COULD BE USEFUL.
	DECLARE xcalced_order_sub_total DECIMAL(10,3) DEFAULT 0.0; -- USING THIS TO TEST HTE APPS MATH ON ORDER CALC.  COULD BE USEFUL.

	DECLARE xcash CHAR(1);

	DECLARE xmodifier_group_price_override DECIMAL (10,3);

	DECLARE logit INT (1);

	-- DECLARE orderItems CURSOR FOR SELECT temp_order_detail_id,sizeprice_id, quantity,name,note FROM TempOrderItems;
	DECLARE orderItems CURSOR FOR SELECT temp_order_detail_id,sizeprice_id, quantity,name,note,points_used,amount_off_from_points,external_detail_id FROM TempOrderItems;

		INSERT INTO Errors values(null,CONCAT(xraw_stamp,' Starting SMAWSP_CREATE_ORDER'),CONCAT('order:',in_order_id),'','',now());

		-- set order information
		SELECT order_id,ucid,user_id,merchant_id,order_type INTO xorder_id,xucid,xuser_id,xmerchant_id,xorder_type FROM Orders WHERE order_id = in_order_id;

		-- get skin properties
		SELECT brand_id, donation_active INTO xbrand_id, xskin_donation_active FROM Skin WHERE skin_id = in_skin_id;



mainBlock:BEGIN

    SET logit = 1;

		-- Get user properties
		SELECT 1,email INTO xuser_found,xemail FROM User WHERE user_id = xuser_id and logical_delete = 'N';

		IF xuser_found IS NULL THEN
		    SET out_return_id = 100;
				SET out_message = 'SERIOUS_DATA_ERROR_USER_ID_DOES_NOT_EXIST';
				IF logit THEN
					INSERT INTO Errors values(null,CONCAT(xraw_stamp,' SERIOUS APP ERROR! THIS USER DOES NOT EXIST'),CONCAT('user:',xuser_id),'','',now());
				END IF;
				LEAVE mainBlock;
		END IF;

		SELECT m.name,m.numeric_id INTO xmerchant_name,xnumeric_id FROM Merchant m WHERE m.merchant_id = xmerchant_id;

		IF xemail = CONCAT(xnumeric_id,'_manager@dummy.com') THEN
			SET linebuster = 'Y';
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' LINE BUSTER!'),CONCAT('user:', xuser_id),CONCAT('merch:', xmerchant_id),'',now());
		END IF;

		-- get total tax from merchant (this is jsut a place holder now since items may have different tax rates)
		SELECT sum(rate) INTO xtax_total_rate FROM `Tax` WHERE merchant_id = xmerchant_id AND tax_group = 1 AND logical_delete = 'N';

		IF logit THEN
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' SUCCESS! WITH USER AND MERCHANT.'),CONCAT('user:', xuser_id),CONCAT('merch:',xmerchant_name),CONCAT('tax 1',xtax_total_rate),now());
		END IF;

	foundBlock:BEGIN
		-- START TRANSACTION

			-- now loop through the temporary table
			OPEN orderItems;

			BEGIN
				DECLARE noMoreRows int(1) DEFAULT 0;
				DECLARE xcalced_item_sub_total DECIMAL (10,3) DEFAULT 0.00; -- to hold the total price of this item (mods and anything else) before tax caculations
				DECLARE CONTINUE HANDLER FOR NOT FOUND
				BEGIN
					SET noMoreRows = 1;
				END;

				-- GET NUMBER OF ITEMS IN THE TABLE
				SELECT COUNT(sizeprice_id) INTO xnum_of_temp_order_items FROM TempOrderItems;

				SET xcalced_order_total = 0.0;

			-- now loop through each item in the temp table
			orderInsertLoop:LOOP

				-- FETCH orderItems INTO  xtemp_order_detail_id,xsizeprice_id,xitem_quantity, xitem_nameofuser,xitem_note;
				FETCH orderItems INTO  xtemp_order_detail_id,xsizeprice_id,xitem_quantity, xitem_nameofuser,xitem_note,xitem_points_used,xitem_amount_off_from_points,xitem_external_detail_id;
				IF noMoreRows THEN
					leave orderInsertLoop;
				END IF;
				IF logit THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got info from temporary table'),CONCAT('SPid:', xsizeprice_id),CONCAT('qty:', xitem_quantity),CONCAT(xitem_nameofuser,':',xitem_note),now());
				END IF;
				SELECT a.item_id,a.size_id,b.item_name,b.item_print_name,a.price,a.active,a.merchant_id,d.size_name,d.size_print_name,a.external_id,a.tax_group,c.menu_type_name,b.active,c.active,c.menu_type_id
				INTO xitem_id,xsize_id,xitem_name,xitem_print_name,xitem_price,xitem_price_active,xmenu_item_owner,xsize_name,xsize_print_name,xitem_external_id,xitem_tax_group,xmenu_type_name,xitem_active,xmenu_type_active,xmenu_type_id
				FROM Item_Size_Map a, Item b, Menu_Type c, Sizes d
				WHERE a.item_id = b.item_id AND a.item_size_id = xsizeprice_id AND b.menu_type_id = c.menu_type_id AND a.size_id = d.size_id;

				IF xitem_id IS NULL THEN
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY! ERROR, ITEM NO LONGER EXISTS FROM APP!'),'','','',now());
					SET out_return_id = 705;
					SET out_message = 'DATA_INTEGRITY_ERROR_APP_ITEM_NO_LONGER_EXISTS';
					leave foundBlock;
				END IF;

				IF xitem_price_active = 'N' OR xitem_active = 'N' OR xmenu_type_active = 'N' THEN
					-- SHOULD NEVER HAPPEN SINCE I SEND THE ACTIVE ITEMS TO THE APP WHEN THE CUSTOMER STARTS THE APP
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY! ERROR ITEM NOT ACTIVE FROM APP!'),CONCAT('SPid:', xsizeprice_id),CONCAT('qty:', xitem_quantity),
												CONCAT(xitem_id,':',xsize_id,':',xitem_name,':',xitem_price,':',xitem_price_active),now());
					SET out_return_id = 710;
					SET out_message = 'DATA_INTEGRITY_ERROR_APP_ITEM_NOT_ACTIVE';
					leave foundBlock;
				END IF;

				-- make sure the item is owned by the merchant referenced in the order
				IF xorder_type = 'D' THEN
					SELECT menu_id INTO xmenu_id FROM Merchant_Menu_Map WHERE merchant_id = xmerchant_id AND merchant_menu_type = 'delivery' AND logical_delete = 'N';
				ELSE
					SELECT menu_id INTO xmenu_id FROM Merchant_Menu_Map WHERE merchant_id = xmerchant_id AND merchant_menu_type = 'pickup' AND logical_delete = 'N';
				END IF;

				-- get version of menu
				SELECT version INTO xmenu_version FROM Menu WHERE menu_id = xmenu_id AND logical_delete = 'N';

				IF xmenu_version > 2.0 THEN
					SET xmenu_merchant_id = xmerchant_id;
				ELSE
					SET xmenu_merchant_id = 0;
				END IF;

				IF logit THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got item info from Item_Size_Map'),CONCAT('SizePriceId:', xsizeprice_id,'   qty:', xitem_quantity),CONCAT('item_id:', xitem_id,'  size_id:', xsize_id),CONCAT('name:',xitem_name,'  price:',xitem_price,'  price_active:',xitem_price_active),now());
			 	END IF;

				IF xmenu_item_owner != xmenu_merchant_id THEN
					-- should never happen
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY ERROR! ITEM NOT OWNED BY THIS MERCHANTANT!'),CONCAT('merchant_id: ', xmenu_merchant_id),CONCAT('owner id:', xmenu_item_owner),'',now());
					SET out_return_id = 710;
					SET out_message = 'DATA_INTEGRITY_ERROR_ITEM_NOT_OWNED_BY_SUBMITTED_MERCHANT';
					leave foundBlock;
				END IF;

				-- xitem_sub_total is a useless field
				SET xitem_sub_total = xitem_quantity*xitem_price;
				SET xcalced_item_sub_total = xitem_price;

				INSERT INTO `Order_Detail` ( `order_id`, `item_size_id`,`external_id`,`external_detail_id`,`menu_type_name`,`size_name`,`size_print_name`,`item_name`,`item_print_name`,`name`,`note`,`quantity`,`price`,`item_total`,`created`)
				VALUES (xorder_id, xsizeprice_id, xitem_external_id, xitem_external_detail_id, xmenu_type_name, xsize_name, xsize_print_name, xitem_name, xitem_print_name, xitem_nameofuser,xitem_note,xitem_quantity,xitem_price,xitem_sub_total,now());

				-- get last inserted id so we can associate the modifications with a particular item
				SELECT LAST_INSERT_ID() INTO xorder_detail_id;

				IF logit THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Item Added to Order_Items'),CONCAT('user:',xuser_id),CONCAT(xitem_id,':',xsize_id,':',xitem_quantity,':',xitem_price,':',xitem_sub_total),CONCAT('Order:',xorder_id),now());
				END IF;

				-- Here starts the modification code
				BEGIN

					DECLARE noMoreModRows int(1) DEFAULT 0;
					DECLARE xmod_sizeprice_id int(11);
					DECLARE xmod_price DECIMAL(10,3) DEFAULT 0.0;
					DECLARE xmod_qty int(11);
					DECLARE xmod_total_price DECIMAL(10,3);
					DECLARE xmodifier_item_name VARCHAR(50);
					DECLARE xmodifier_item_print_name VARCHAR(50);
					DECLARE xmodifier_item_id int(11);
					DECLARE xmodifier_item_priority int(11);
					DECLARE xmodifier_item_external_id VARCHAR(50) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_group_external_id VARCHAR(50) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_concat_external_id VARCHAR(100) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_group_name VARCHAR(50);
					DECLARE xmodifier_group_id int(11);
					DECLARE xmodifier_type CHAR(2) DEFAULT 'T';
					DECLARE found_comes_with int(1);
					DECLARE xcomes_with CHAR(1) DEFAULT 'N';
					DECLARE xhold_it CHAR(1) DEFAULT 'N';
					DECLARE xhold_it_modifier_group_id int(11);
					DECLARE xhold_it_modifier_group_name VARCHAR(50);

					DECLARE order_item_mods CURSOR FOR
								SELECT a.mod_sizeprice_id,b.modifier_price,a.mod_quantity,(b.modifier_price*a.mod_quantity) as mod_total_price,c.modifier_item_name,c.modifier_item_print_name,
										c.modifier_item_id,d.modifier_group_name,d.modifier_type,d.modifier_group_id,b.external_id,c.priority,d.external_modifier_group_id
								FROM TempOrderItemMods a, Modifier_Size_Map b, Modifier_Item c, Modifier_Group d
								WHERE a.mod_sizeprice_id = b.modifier_size_id AND b.modifier_item_id = c.modifier_item_id AND c.modifier_group_id = d.modifier_group_id AND a.temp_order_detail_id = xtemp_order_detail_id;

					DECLARE CONTINUE HANDLER FOR NOT FOUND
					BEGIN
						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' WE GOT A NOT FOUND IN TEH MODIFIER CURSOR'),'','','',now());
						END IF;
						SET noMoreModRows = 1;
					END;

					OPEN order_item_mods;

					modInsertLoop:LOOP
						FETCH order_item_mods INTO xmod_sizeprice_id,xmod_price,xmod_qty,xmod_total_price, xmodifier_item_name, xmodifier_item_print_name, xmodifier_item_id, xmodifier_group_name, xmodifier_type,xmodifier_group_id, xmodifier_item_external_id, xmodifier_item_priority, xmodifier_group_external_id;
						IF noMoreModRows THEN
							leave modInsertLoop;
						END IF;
						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got MODIFICATION price from temporary table'),CONCAT('mod_sizeprice_id:', xmod_sizeprice_id,'    mod_id:', xmodifier_item_id),CONCAT('mod_group_id: ', xmodifier_group_id),CONCAT('qty:', xmod_qty,'   total_mod_price:', xmod_total_price),now());
						END IF;

						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' the external values'),CONCAT('group_external:', xmodifier_group_external_id),CONCAT('item_external:', xmodifier_item_external_id),CONCAT('concat:', xmodifier_concat_external_id),now());
						END IF;

						IF xmodifier_type = 'Q' AND xmod_qty > 1 THEN
							SET xitem_quantity = xmod_qty;
						END IF;

						-- now determine if this is a comes with item, if so then deduct a single price unit from the total mod price (quantity*unit price).  the message builder will detmine if its shown on the ticket.  had to still include it in the order otherwiese the message builder will think its being held, as in HOLD the mayo.
						-- somthing else:  so if the sandwich is ham/swiss and the person swaps out cheddar for swiss, they will get charged for cheddar.  we made a concious decision NOT to deal with swaps.  tough luck kind of thing.

						-- but..... if the comes with modifier doesn't exist, we could test to see if an added modifier is in the same group as the comes with modifier, if so compare the price
						--       and if they're the same then subtract.......  hmmmmm..........  maybe this is for 2.5

						-- first selection zero price override?????

						-- we can skip this code if the price is zero already though
						-- IF xmod_price > 0.00 THEN
						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' CHECK IF MOD IS ON THE COMES WITH LIST'),CONCAT('mod_sizeprice_id:', xmod_sizeprice_id,'    mod_id:', xmodifier_item_id),CONCAT('mod_group_id: ', xmodifier_group_id),CONCAT('qty:', xmod_qty,'   total_mod_price:', xmod_total_price),now());
						END IF;
							set found_comes_with = 0;
							set xcomes_with = 'N';
							-- is this on the list?
							SELECT 'Y' INTO xcomes_with FROM Item_Modifier_Item_Map WHERE item_id = xitem_id AND modifier_item_id = xmodifier_item_id AND logical_delete = 'N';
							IF logit THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' comes with is: ', xcomes_with),'','','',now());
							END IF;
							IF xcomes_with = 'Y' THEN
								IF logit THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Its on the comes with list'),NULL,NULL,NULL,now());
								END IF;
								-- with the addition of the price_override functionality we only need to do this calculation if price_override is 0.00.  if its more than zero, we'll let ht price get settled
								-- in the adjustment section below.
								SELECT price_override INTO xmodifier_group_price_override FROM Item_Modifier_Group_Map WHERE modifier_group_id = xmodifier_group_id AND item_id = xitem_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;

								IF xmodifier_group_price_override = 0.00 THEN
									-- no price override so let the comes with price logic work
									SET xmod_total_price = xmod_total_price - xmod_price;
								END IF;
							END IF;

							-- and now for the HACK.
							-- the not found handler is screwing things up here when there was no rows found for comes with
							-- so we need to set the noMoreModRows to '0' to keep the loop going
							SET noMoreModRows = 0;
						-- END IF;
						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' about to do the insert for modifier'),CONCAT('name:', xmodifier_item_name),CONCAT('s_name:', xmodifier_item_print_name),'',now());
						END IF;

						-- now insert it into the order_detail_mod table
						INSERT INTO `Order_Detail_Modifier` (`order_detail_id`,`modifier_size_id`,`external_id`,`modifier_item_id`,`modifier_item_priority`,`modifier_group_id`,`modifier_group_name`,`mod_name`,
										`mod_print_name`,`modifier_type`,`comes_with`,`hold_it`,`mod_quantity`,`mod_price`,`mod_total_price`,`created`)
						VALUES (	xorder_detail_id,xmod_sizeprice_id, xmodifier_item_external_id,xmodifier_item_id,xmodifier_item_priority,xmodifier_group_id,
 								xmodifier_group_name ,xmodifier_item_name,xmodifier_item_print_name,xmodifier_type,xcomes_with,xhold_it,xmod_qty,xmod_price,xmod_total_price,NOW());

						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' modifier INSERTED!'),'','','',now());
						END IF;

						SET xcalced_item_sub_total = xcalced_item_sub_total + xmod_total_price;

					END LOOP; -- mod insert loop

					CLOSE order_item_mods;

					-- NOW FIGURE OUT WHAT THE 'HOLD THE' ITEMS ARE AND ADD THEM TO THE ORDER DETAIL
					holdtheBlock:BEGIN

						DECLARE comes_with_items CURSOR FOR SELECT a.modifier_item_id, b.modifier_item_name, b.modifier_item_print_name,b.modifier_group_id,b.priority,c.modifier_type
												FROM Item_Modifier_Item_Map a, Modifier_Item b, Modifier_Group c
												WHERE a.item_id = xitem_id AND a.modifier_item_id = b.modifier_item_id AND b.modifier_group_id = c.modifier_group_id AND a.logical_delete = 'N';
						SET noMoreModRows = 0;
						IF logit THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' starting the hold it loop'),'','','',now());
						END IF;
						OPEN comes_with_items;
						holdtheInsertLoop:LOOP
							FETCH comes_with_items INTO xmodifier_item_id,xmodifier_item_name,xmodifier_item_print_name,xhold_it_modifier_group_id,xmodifier_item_priority,xmodifier_type;
							IF noMoreModRows THEN
								leave holdtheInsertLoop;
							END IF;
							SET xhold_it = 'Y';

              IF logit THEN
                  INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' looping'),xmodifier_item_id,xmodifier_item_name,CONCAT('order_datail: ', xorder_detail_id),now());
              END IF;

              SELECT 'N' INTO xhold_it FROM `Order_Detail_Modifier` WHERE order_detail_id = xorder_detail_id AND modifier_item_id = xmodifier_item_id;

              IF logit THEN
                  INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' after select N'),'','',CONCAT('order_datail: ', xorder_detail_id),now());
              END IF;
              IF xhold_it = 'Y' THEN
                  IF logit THEN
                              INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' *****WE HAVE A HOLD IT!******'),xmodifier_item_id,xmodifier_item_name,'',now());
                  END IF;
                  -- short name sub for null short name.   legacy stuff.  shouldn't be nulls going forward.
                  IF xmodifier_item_print_name IS NULL THEN
                    -- SET xmodifier_item_name = xmodifier_item_print_name;
                    SET xmodifier_item_print_name = xmodifier_item_name;
                  END IF;

                  -- for POS we need the maping id of the held item so....
                  SET xmod_sizeprice_id = null;
                  SELECT modifier_size_id,external_id INTO xmod_sizeprice_id, xmodifier_item_external_id FROM Modifier_Size_Map WHERE modifier_item_id = xmodifier_item_id AND size_id = xsize_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;
                  IF xmod_sizeprice_id IS NULL THEN
                      SELECT modifier_size_id, external_id INTO xmod_sizeprice_id, xmodifier_item_external_id FROM Modifier_Size_Map WHERE modifier_item_id = xmodifier_item_id AND size_id = 0 AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;
                  END IF;
                  IF xmod_sizeprice_id IS NULL THEN
                    -- ok looks like we have an orphaned Item_Modifier_Item record, so we'll just skip it.
                      INSERT INTO Errors VALUES (null,xraw_stamp,'EMAIL ERROR',
                        CONCAT('NULL value for mod_size_map_id. Skipping hold it insert. modifier_item_id: ', xmodifier_item_name,'  ',xmodifier_item_id),
                        CONCAT('item_id:',xitem_id,'    merchant_id:', xmenu_merchant_id),now());
                  ELSE
                    IF xmodifier_type != 'Q' THEN
                      INSERT INTO `Order_Detail_Modifier` (`order_detail_id`,`modifier_size_id`,`external_id`,`modifier_item_id`,`modifier_item_priority`,`modifier_group_id`,`mod_name`,`mod_print_name`,`modifier_type`,
                            `comes_with`,`hold_it`,`mod_quantity`,`mod_price`,`mod_total_price`,`created`)
                      VALUES (	xorder_detail_id, xmod_sizeprice_id, xmodifier_item_external_id,xmodifier_item_id,xmodifier_item_priority,xhold_it_modifier_group_id,xmodifier_item_name,xmodifier_item_print_name,xmodifier_type,'H',xhold_it,0,0.00,0.00,now());
                    END IF;
                  END IF;
							ELSE
								IF logit THEN
									INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' do not hold it!'),xmodifier_item_id,xmodifier_item_name,'',now());
								END IF;
							END IF;
							SET noMoreModRows = 0;
						END LOOP;

					END; -- HOLD THE BLOCK

					-- now do any price recalcs for zero price override or mod group max price.
					-- get the mod groups that have some rules
					-- determine total price on each of those groups in the order
					-- make subtotal changes if necessary
						-- add a row in the modifier details table for this price change?  maybe. yes
						-- need a dummy modifier that holds this place in the order_details table?   should NOT display on receipt to user

					-- maybe can do a check here for a free/promo item
	priceadjustblock:BEGIN

						DECLARE xmodifier_group_price_max DECIMAL (10,3);
						DECLARE xgroup_total_price_for_item DECIMAL (10,3);
						DECLARE xadjustment_amount DECIMAL (10,3);

						DECLARE order_item_mod_groups CURSOR FOR SELECT modifier_group_id,price_override,price_max FROM Item_Modifier_Group_Map
									WHERE item_id = xitem_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id AND
									modifier_group_id IN (SELECT DISTINCT modifier_group_id FROM Order_Detail_Modifier WHERE order_detail_id = xorder_detail_id AND ( modifier_type LIKE 'I%' OR modifier_type = 'T' OR modifier_type = 'S' ));

						DECLARE CONTINUE HANDLER FOR NOT FOUND
							SET noMoreModRows = 1;

						OPEN order_item_mod_groups;
						LOOP
							SET noMoreModRows = 0;
							FETCH order_item_mod_groups INTO xmodifier_group_id, xmodifier_group_price_override, xmodifier_group_price_max;
							IF noMoreModRows THEN
								LEAVE priceadjustblock;
							END IF;
							IF logit THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' we have fetched Price Overrides'), xmodifier_group_id, xmodifier_group_price_override, xmodifier_group_price_max,now());
							END IF;

							-- so now see what the total amount spent on the group is.  problem is that a comes with item wont be charged so it wont be figured in.  HOW TO SOLVE FOR THIS?????
							--  maybe in price calculations above we need to see if the modifier_item is part of item_group_map that has an override, if so, we do charge for the item above and let it work
							-- out in the logic here.
							SELECT SUM(mod_total_price) INTO xgroup_total_price_for_item FROM Order_Detail_Modifier WHERE modifier_group_id = xmodifier_group_id AND order_detail_id = xorder_detail_id;

							IF logit THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' we have summed price override'), xgroup_total_price_for_item, '', '',now());
							END IF;

							IF xmodifier_group_price_override > 0.00 THEN
								SET xadjustment_amount = xmodifier_group_price_override;
								IF xgroup_total_price_for_item < xmodifier_group_price_override THEN
									SET xadjustment_amount = xgroup_total_price_for_item;
								END IF;

								-- add row to order_detail_modifier table
								IF xadjustment_amount > 0.00 THEN
									INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
										VAlUES (xorder_detail_id,0, xmodifier_group_id,'price adjustment','override','A','N',1,-xadjustment_amount,-xadjustment_amount,NOW());
									-- adjust xitem_sub_total
									SET xcalced_item_sub_total = xcalced_item_sub_total - xadjustment_amount;

									-- have to adjust xgroup_total_price_for_item here so it works for the next section.  very low probability of this happening though
									SET xgroup_total_price_for_item = xgroup_total_price_for_item - xadjustment_amount;
								END IF;

							END IF;
							IF xmodifier_group_price_max IS NOT NULL AND xgroup_total_price_for_item > xmodifier_group_price_max THEN
								-- add row in modifier details table for the adjustment
								SET xadjustment_amount = xgroup_total_price_for_item - xmodifier_group_price_max;
								INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
									VAlUES (xorder_detail_id,0, xmodifier_group_id,'price adjustment','group max','A','N',1,-xadjustment_amount,-xadjustment_amount,NOW());
								-- adjust xitem_sub_total
								SET xcalced_item_sub_total = xcalced_item_sub_total - xadjustment_amount;
							END IF;
						END LOOP; -- loop of mod groups in order
					END;  -- priceadjustblock

	paywithpointsblock:BEGIN
						IF xitem_points_used > 0 THEN
								SELECT charge_modifiers_loyalty_purchase INTO charge_modifiers FROM Brand_Loyalty_Rules WHERE brand_id = xbrand_id;
                -- INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' WE HAVE GOTTEN THE CHARGE MODS VALUE'),CONCAT('charge_modifiers value:', charge_modifiers),null, null,now());
                IF charge_modifiers = 0 THEN
                    SET xitem_amount_off_from_points = xcalced_item_sub_total;
                END IF;

							INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
									VAlUES (xorder_detail_id,0, 0,'price adjustment','points','P','N',xitem_points_used,-xitem_amount_off_from_points,-xitem_amount_off_from_points,NOW());
							-- adjust xitem_sub_total
							SET xcalced_item_sub_total = xcalced_item_sub_total - xitem_amount_off_from_points;
						END IF;
					END;  -- paywithpointsblock

					-- xcalced_item_sub_total should be loaded up with the totals from the modifiers for this item by the time the code gets to here.

					--  here is where i do the tax for the item
					-- get tax rate for this item from the tax table
					IF xitem_tax_group = 0 THEN
 						SET xitem_tax_rate = 0.00;
					ELSE
						SELECT sum(rate) INTO xitem_tax_rate FROM `Tax` WHERE merchant_id = xmerchant_id AND tax_group = xitem_tax_group AND logical_delete = 'N';
						IF (xitem_tax_rate IS NULL AND xitem_tax_group > 1) THEN
							SELECT sum(rate) INTO xitem_tax_rate FROM `Tax` WHERE merchant_id = xmerchant_id AND tax_group = 1 AND logical_delete = 'N';
							INSERT INTO Errors VALUES (null,xraw_stamp,'EMAIL ERROR',
								CONCAT( 'NO TAX GROUP FOR THIS ITEM Defaulting to 1 group ','item_id: ', xitem_id,'  tax group: ',xitem_tax_group),
								CONCAT('size_price_id:', xsizeprice_id),now());
						END IF;
					END IF;

					-- determine if there is a quantity modifier of more than 1 and adjust
					IF xitem_quantity > 1 THEN
						SET xcalced_item_sub_total = xitem_quantity * xcalced_item_sub_total;
						SET xitem_sub_total = xitem_quantity*xitem_price;
					END IF;

					-- now get the tax amount for this item and add it to the running total tax amit
					SET xtax_running_total_amt = xtax_running_total_amt + ((xitem_tax_rate/100) * xcalced_item_sub_total);

					UPDATE Order_Detail SET item_total_w_mods = xcalced_item_sub_total,item_tax = ((xitem_tax_rate/100) * xcalced_item_sub_total),item_total = xitem_sub_total,quantity = xitem_quantity WHERE order_detail_id = xorder_detail_id;

					IF logit Then
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' TAX for item calculated'),CONCAT('rate:', xitem_tax_rate),CONCAT('subtotal: ', xcalced_item_sub_total),CONCAT('item tax total:', (xitem_tax_rate/100) * xcalced_item_sub_total),now());
					END IF;

					-- now update the running total
					SET xcalced_order_sub_total = xcalced_order_sub_total + xcalced_item_sub_total;

				END; -- MODS SECTION

			END LOOP;

			CLOSE orderItems;
		END;

		-- check fixed taxes
    BEGIN
        SELECT amount INTO xfixed_tax_amount FROM Fixed_Tax WHERE merchant_id = xmerchant_id;
        INSERT INTO Errors VALUES (null,xraw_stamp, CONCAT('fixed tax amount:', xfixed_tax_amount),'','',now());
    END;


    IF logit THEN
        INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ORDER TOTALS '), CONCAT('tax_total:', xtax_running_total_amt),CONCAT('calced_sub_total', xcalced_order_sub_total),'',now());
    END IF;

    -- get new totals
    SELECT SUM(item_total_w_mods),SUM(item_tax),SUM(quantity) INTO xcalced_order_sub_total, xtax_running_total_amt, xnum_of_temp_order_items FROM Order_Detail WHERE `order_id` = xorder_id and `logical_delete` = 'N';

    SET xtax_running_total_amt = xtax_running_total_amt + xfixed_tax_amount;
    INSERT INTO Errors VALUES (null,xraw_stamp, CONCAT('running total tax amount:', xtax_running_total_amt),'','',now());
		UPDATE `Orders` SET `order_amt` = xcalced_order_sub_total,`item_tax_amt`= xtax_running_total_amt, `order_qty` = xnum_of_temp_order_items WHERE `order_id` = xorder_id;

		-- if we got here then we know all the inserts suceeded and we can commit all the changes
		-- COMMIT;
		SET out_return_id = xorder_id;
		SET out_message = 'Success';

		IF logit THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Returned id from creating the order is'),CONCAT('user:',xuser_id),concat('Returned order id:',out_return_id),'',now());
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' completion of found block in SMAW_CREATE_ORDER'),xorder_id,out_message,'',now());
		END IF;
	END;  -- end found block
  END; -- end main block
END
 ;;
delimiter ;
