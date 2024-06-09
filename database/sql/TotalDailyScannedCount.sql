use [OMAN_CSI_AUG]

declare @StartTime VARCHAR(15) 
declare @ENDTime VARCHAR(15)
set @StartTime = '2024-01-17 00:00:01.000'
set @ENDTime =   '2024-01-17 23:59:59.999';

WITH bT AS
(
	SELECT BookletName AS 'SubjectCode', FormNo FROM BookletType WITH (NOLOCK)
),
bCSI AS
(
	SELECT subjectcode,
	SUM(CASE WHEN scanned = 1 and CONVERT(DATETIME,ScanDateTime,21) between CONVERT(DATETIME,@StartTime,21) and CONVERT(DATETIME,@ENDTime,21) THEN 1 ELSE 0 END) AS 'ScannedTodayCSI',
	SUM(CASE WHEN scanned = 1 THEN 1 ELSE 0 END) AS 'CSINormalScanned'
	FROM  OMAN_CSI_AUG.dbo.booklet WITH (NOLOCK) GROUP BY SubjectCode
),
bRanCSI AS
(
	SELECT subjectcode,
	SUM(CASE WHEN scanned = 1 and CONVERT(DATETIME,ScanDateTime,21) between CONVERT(DATETIME,@StartTime,21) and CONVERT(DATETIME,@ENDTime,21) THEN 1 ELSE 0 END) AS 'ScannedTodayRandomCSI',
	SUM(CASE WHEN scanned = 1 THEN 1 ELSE 0 END) AS 'CSIRandomScanned'
	FROM  OMAN_CSI_RANDOM.dbo.booklet WITH (NOLOCK) GROUP BY SubjectCode
),
track AS
(
	SELECT bt.BookletName,
	SUM(CASE WHEN f.Received = 1 and ReceiptStatus = 'Blank' THEN 1 ELSE 0 END) AS 'TotalBlank',
	SUM(CASE WHEN f.Received = 0 and ReceiptStatus = '' THEN 1 ELSE 0 END) AS 'ToLog',
	SUM(CASE WHEN f.Received = 1 and ReceiptStatus = 'Scannable' and f.scanned = 0 THEN 1 ELSE 0 END) AS 'ToActualScan',
	SUM(CASE WHEN f.Received = 1 THEN 1 ELSE 0 END) AS 'TotalLogged',
	SUM(CASE WHEN f.Scanned = 1 THEN 1 ELSE 0 END) AS 'TrackerTotalScanned',
	SUM(CASE WHEN f.Received = 1 and ReceiptStatus = 'Scannable' and CONVERT(DATETIME,ReceiptDateTime,21) between CONVERT(DATETIME,@StartTime,21) and CONVERT(DATETIME,@ENDTime,21) THEN 1 ELSE 0 END) AS 'TotalLoggedToday',
	count(f.Barcode) AS 'Total'
	FROM tracker.dbo.form f WITH (NOLOCK) LEFT JOIN tracker.dbo.FormType ft WITH (NOLOCK) ON f.FormTypeID = ft.FormTypeID
		LEFT JOIN OMAN_CSI_AUG.dbo.BookletType bt WITH (NOLOCK) ON bt.TrackerFormTypeId = ft.FormTypeID GROUP BY BookletName
),
display as
(
	SELECT
	FormNo,ab.SubjectCode, 
	ISNULL(a.ScannedTodayCSI,0) 'ScannedTodayCSI', 
	ISNULL(b.ScannedTodayRandomCSI,0) 'ScannedTodayRandomCSI',
	ISNULL(c.TotalBlank,0) 'TotalBlank', 
	ISNULL(c.ToLog,0) 'ToLog', 
	ISNULL(c.ToActualScan,0) 'ToActualScan', 
	ISNULL(c.TrackerTotalScanned,0) 'TrackerTotalScanned', 
	ISNULL(CASE WHEN (c.TrackerTotalScanned = a.CSINormalScanned) THEN a.CSINormalScanned ELSE (a.CSINormalScanned + b.CSIRandomScanned) END,0) AS 'CSITotalScanned',
	ISNULL(a.CSINormalScanned,0) 'CSINormalScanned', 
	ISNULL(b.CSIRandomScanned,0) 'CSIRandomScanned', 
	ISNULL(c.TotalLogged,0) 'TotalLogged', 
	ISNULL(c.TotalLoggedToday,0) 'TotalLoggedToday',
	ISNULL(c.Total,0) 'Total'

	FROM bT ab LEFT JOIN track c ON ab.SubjectCode = c.BookletName
	LEFT JOIN bCSI a ON ab.SubjectCode = a.SubjectCode
	LEFT JOIN bRanCSI b ON ab.SubjectCode = b.SubjectCode
)
SELECT * FROM display 
--WHERE ToLog <> 0
UNION ALL
SELECT 'Total','<----->', SUM(ScannedTodayCSI),SUM(ScannedTodayRandomCSI),SUM(totalblank),SUM(tolog),SUM(ToActualScan),SUM(TrackerTotalScanned),SUM(CSITotalScanned),
SUM(CSINormalScanned),SUM(CSIRandomScanned),SUM(TotalLogged),SUM(TotalLoggedToday),SUM(Total) FROM display 
ORDER BY FormNo

	

	-- List all MC Item Counts
DECLARE @columns		VARCHAR(MAX);
	DECLARE @sql_raw		VARCHAR(MAX);

	SELECT @columns = COALESCE(@columns + ',[' + column_name + ']'
		, '[' + column_name + ']')
	from oman_op.information_schema.COLUMNS
	where table_name = 'OMRBooklet' and right(column_name,2) = 'P1';

	-- Raw reporting table
	SET @sql_raw = '
		
		select Question, count(*) ''MCCount'' from
		(
			select *
			from
			(select ' + @columns + '
			from oman_op.dbo.omrbooklet) p unpivot
			(Result For Question in (' + @columns + ') 
			) as unpvt where result = ''0'' or len(result) > 1
		) a group by Question
		order by Question
		';

	--EXEC (@sql_raw);
