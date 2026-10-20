import zlib from 'zlib';

export const decodePng = ( buffer ) => {
	let at = 8;
	let head = null;
	const parts = [];

	while ( at < buffer.length ) {
		const length = buffer.readUInt32BE( at );
		const type = buffer.toString( 'ascii', at + 4, at + 8 );
		const body = buffer.subarray( at + 8, at + 8 + length );

		if ( 'IHDR' === type ) {
			head = {
				width: body.readUInt32BE( 0 ),
				height: body.readUInt32BE( 4 ),
				depth: body[ 8 ],
				colour: body[ 9 ],
				interlace: body[ 12 ],
			};
		} else if ( 'IDAT' === type ) {
			parts.push( Buffer.from( body ) );
		} else if ( 'IEND' === type ) {
			break;
		}

		at += 12 + length;
	}

	if ( ! head || 8 !== head.depth || head.interlace ) {
		throw new Error( 'unexpected PNG: ' + JSON.stringify( head ) );
	}

	const channels = { 0: 1, 2: 3, 4: 2, 6: 4 }[ head.colour ];
	const stride = head.width * channels;
	const raw = zlib.inflateSync( Buffer.concat( parts ) );
	const out = Buffer.alloc( head.height * stride );

	for ( let y = 0; y < head.height; y += 1 ) {
		const filter = raw[ y * ( stride + 1 ) ];
		const line = raw.subarray( y * ( stride + 1 ) + 1, ( y + 1 ) * ( stride + 1 ) );
		const here = y * stride;
		const above = here - stride;

		for ( let i = 0; i < stride; i += 1 ) {
			const left = i >= channels ? out[ here + i - channels ] : 0;
			const up = y ? out[ above + i ] : 0;
			const corner = y && i >= channels ? out[ above + i - channels ] : 0;
			let value = line[ i ];

			if ( 1 === filter ) {
				value += left;
			} else if ( 2 === filter ) {
				value += up;
			} else if ( 3 === filter ) {
				value += ( left + up ) >> 1;
			} else if ( 4 === filter ) {
				const p = left + up - corner;
				const dl = Math.abs( p - left );
				const du = Math.abs( p - up );
				const dc = Math.abs( p - corner );
				value += dl <= du && dl <= dc ? left : ( du <= dc ? up : corner );
			}

			out[ here + i ] = value & 0xff;
		}
	}

	return {
		width: head.width,
		height: head.height,
		at: ( x, y ) => {
			const i = y * stride + x * channels;
			return [ out[ i ], out[ i + 1 ], out[ i + 2 ] ];
		},
	};
};

