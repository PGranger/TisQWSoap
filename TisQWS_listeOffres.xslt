<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:d="http://schemas.microsoft.com/ado/2007/08/dataservices" 
	xmlns:m="http://schemas.microsoft.com/ado/2007/08/dataservices/metadata" 
	xmlns:geo="http://www.georss.org/georss">

	<xsl:output encoding="utf-8" indent="yes" version="1.0" method="xml" />
	<xsl:strip-space elements="*" />
	<xsl:preserve-space elements="pre | code" />

	<xsl:template match="feed">
		<feed>
			<xsl:for-each select="entry">
				<xsl:sort select="content/m:properties/d:ObjectTypeName" />
				<xsl:sort select="content/m:properties/d:commune" />
				<xsl:sort select="content/m:properties/d:SyndicObjectID" />
				<xsl:apply-templates select="." />
			</xsl:for-each>
		</feed>
	</xsl:template>
	
	<xsl:template match="d:SyndicObjectID">
			<xsl:choose>
				
				<!-- D'abord, c'est du GDF ou clé vacances ? -->
				<xsl:when test="../d:labels[contains(text(),'Gîtes de France')] or ../d:labels[contains(text(),'Locations CléVacances')] or ../d:labels[contains(text(),'Clévacances')]">
					<xsl:choose>
						<!-- Si c'est clé vacances on uniformise (Locations CléVacances et Clévacances) -->
						<xsl:when test="../d:labels[contains(text(),'Locations CléVacances')] or ../d:labels[contains(text(),'Clévacances')]">
							<xsl:choose>
								<xsl:when test="../d:otr[contains(text(),'ALT Montluçon')]
											or ../d:otr[contains(text(),'Aumance Troncais')]
											or ../d:otr[contains(text(),'Montluçon')]
											or ../d:otr[contains(text(),'Néris les bains')]">
									<responsable><xsl:value-of select="../d:otr"></xsl:value-of></responsable>
								</xsl:when>
								<xsl:otherwise>
									<responsable>Clévacances</responsable>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<responsable><xsl:value-of select="../d:labels"></xsl:value-of></responsable>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<xsl:choose>
						<!-- Si c'est Bienvenue à la ferme on met à part -->
						<xsl:when test="../d:labels[contains(text(),'Bienvenue à la Ferme')]
										or ../d:labels[contains(text(),'Bienvenue à la ferme')]">
							<responsable>Bienvenue à la ferme</responsable>
						</xsl:when>
						<xsl:otherwise>
							<xsl:choose>
								<!-- Si c'est du Fleur de soleil ou Rando accueil, c'est pour le CDT -->
								<xsl:when test="../d:otr[contains(text(),'Moulins')]">
									<responsable><xsl:value-of select="../d:otr"></xsl:value-of></responsable>
								</xsl:when>
								<xsl:otherwise>
									<xsl:choose>
										<!-- Si c'est sur Moulins, c'est Moulins qui gère -->
										<xsl:when test="../d:labels[contains(text(),'Fleur de soleil')]
														or ../d:labels[contains(text(),'Fleur de Soleil')]
														or ../d:labels[contains(text(),'Rando accueil')]
														or ../d:labels[contains(text(),'Rando Accueil')]
														or ../d:labels[contains(text(),'Accueil paysan')]
														or ../d:labels[contains(text(),'Accueil Paysan')]">
											<responsable>Zone compétence CDT (Hors OT/ALT)</responsable>
										</xsl:when>
										<xsl:otherwise>
											<xsl:choose>
												<!-- HLO ou restauration : délégué aux OT (ou ALT/CDT si hors zone) -->
												<xsl:when test="
													../d:ObjectTypeName[contains(text(),'Hébergements locatifs')] or 
													../d:ObjectTypeName[contains(text(),'Restauration')]">
													<responsable><xsl:value-of select="../d:otr"></xsl:value-of></responsable>
												</xsl:when>
												<xsl:otherwise>
													<!-- Sinon, c'est le CDT -->
													<responsable>Zone compétence CDT (Hors OT/ALT)</responsable>
												</xsl:otherwise>
											</xsl:choose>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:otherwise>
					</xsl:choose>
					
					
				</xsl:otherwise>
				
			</xsl:choose>
		<xsl:element name="{name()}" >
			<xsl:apply-templates select="* | text() | @*"/>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="@*">
		<xsl:copy />
	</xsl:template>
	
	<xsl:template match="*">
		<xsl:element name="{name()}" >
			<xsl:apply-templates select="* | text() | @*"/>
		</xsl:element>
	</xsl:template>
	
</xsl:stylesheet>